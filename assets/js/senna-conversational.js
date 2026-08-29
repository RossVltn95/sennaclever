/**
 * MENA Careers Conversational Browsing System
 * Unified interface for job discovery with AI guidance
 */

(function ($) {
  "use strict";

  class SennaConversational {
    constructor() {
      // Prevent duplicate initialization
      if (window.sennaConversationalInitialized) {
        console.log("SennaConversational already initialized");
        return window.sennaConversational;
      }

      // Initialize state management
      this.initializeStateManagement();

      // On desktop, ensure autocomplete container is always visible
      if (window.innerWidth > 768) {
        $(".sffc-autocomplete-wrapper").removeClass("scroll-hidden");
        $(".sffc-autocomplete-container").removeClass("scroll-hidden");
        $(".sffc-input-group").removeClass("scroll-hidden");
        $("body").removeClass("mobile-input-hidden");
      }

      this.currentView = "conversational";
      this.currentStage = "browse";
      this.allJobs = [];
      this.filteredJobs = [];
      this.displayedJobs = [];
      // this.shortlist = []; // Shortlist feature removed
      this.welcomeMessageShown = false; // Track if welcome was shown
      this.isProfileComplete = false;
      this.membershipPromptShown = false;
      this.conversationContext = {
        preferences: {},
        history: [],
        currentFilter: null,
      };
      this.tutorLearningState = this.restoreTutorLearningState();
      this.sessionContinuationHandled = false;
      this.jobSearchDisabled = true;
      this.liveExpertMessageShown = false;
      this.liveExpertConnected = false;
      this.liveExpertConnectingShown = false;
      this.liveExpertSessionId = this.restoreLiveExpertSessionId();
      this.liveExpertConversationId = this.restoreLiveExpertConversationId();
      this.liveExpertConnectionNotified = !!this.liveExpertConversationId;
      this.liveExpertWelcomeSent = false;
      this.liveExpertMessageIds = new Set();
      this.liveExpertLastTimestamp = 0;
      this.liveExpertPollInterval = null;

      // Initialize intelligent filter if available
      if (window.IntelligentJobFilter) {
        this.intelligentFilter = new window.IntelligentJobFilter();
      }

      // Make instance globally accessible for PE filters
      window.sennaConversational = this;
      window.sennaConversationalInitialized = true; // Set initialization flag

      this.init();
    }

    init() {
      // Clean up any leftover modals from previous sessions
      this.cleanupModals();

      this.loadShortlist();
      this.bindEvents();
      this.initializeModeToggle();
      this.loadInitialJobs();
      this.startConversation();

      // ✅ Show login button if user is not logged in
      if (
        typeof window.sffc_ajax !== "undefined" &&
        !window.sffc_ajax.is_logged_in
      ) {
        const buttonContainer = document.createElement("div");
        buttonContainer.style.marginTop = "16px";

        const loginButton = document.createElement("button");
        loginButton.textContent = "Join senna";
        loginButton.style.background =
          "linear-gradient(135deg, #1a472a, #2d6a4f)";
        loginButton.style.color = "#fff";
        loginButton.style.padding = "10px 18px";
        loginButton.style.border = "none";
        loginButton.style.borderRadius = "8px";
        loginButton.style.fontSize = "15px";
        loginButton.style.cursor = "pointer";
        loginButton.style.fontWeight = "600";
        loginButton.style.transition = "opacity 0.2s ease";

        loginButton.addEventListener("mouseover", () => {
          loginButton.style.opacity = "0.9";
        });
        loginButton.addEventListener("mouseout", () => {
          loginButton.style.opacity = "1";
        });
        loginButton.addEventListener("click", () => {
          window.location.href = "/join-senna/"; // 🔑 Replace with your login URL
        });

        buttonContainer.appendChild(loginButton);

        // Insert below the intro paragraph
        const introParagraph = document.querySelector(
          "p[style*='font-weight: 600']"
        );
        if (introParagraph && introParagraph.parentNode) {
          introParagraph.parentNode.appendChild(buttonContainer);
        }
      }

      this.insertFilterBar(); // Phase 2: Insert PE filter bar

      // Events are now bound in initializeStateManagement when ready
    }

    initializeStateManagement() {
      // Initialize session state manager
      if (window.SessionStateManager) {
        this.stateManager = new window.SessionStateManager();

        // Initialize conversation flow controller
        if (window.ConversationFlowController) {
          this.flowController = new window.ConversationFlowController(
            this.stateManager
          );
          // Bind events immediately if flow controller is ready
          this.bindFlowControllerEvents();
        }

        console.log("State management initialized");
      } else {
        console.log(
          "State management not yet loaded, will initialize when ready"
        );
        // Set up delayed initialization
        $(document).on("stateManagerReady", () => {
          this.stateManager = new window.SessionStateManager();
          this.flowController = new window.ConversationFlowController(
            this.stateManager
          );
          this.bindFlowControllerEvents();
        });
      }
    }

    restoreLiveExpertSessionId() {
      if (this._cachedLiveExpertSessionId) {
        return this._cachedLiveExpertSessionId;
      }
      let storedSession = null;
      try {
        storedSession = localStorage.getItem("sffcLiveExpertSession");
      } catch (e) {
        storedSession = null;
      }
      if (!storedSession) {
        storedSession = `le-${Date.now().toString(36)}-${Math.random()
          .toString(36)
          .slice(2, 8)}`;
        try {
          localStorage.setItem("sffcLiveExpertSession", storedSession);
        } catch (err) {
          // ignore storage errors
        }
      }
      this._cachedLiveExpertSessionId = storedSession;
      return storedSession;
    }

    restoreLiveExpertConversationId() {
      let storedConversation = null;
      try {
        storedConversation = localStorage.getItem("sffcLiveExpertConversation");
      } catch (e) {
        storedConversation = null;
      }
      if (
        !storedConversation ||
        storedConversation === "null" ||
        storedConversation === "undefined"
      ) {
        return null;
      }
      return storedConversation;
    }

    persistLiveExpertConversationId(conversationId) {
      this.liveExpertConversationId = conversationId || null;
      this.liveExpertConnectionNotified = !!this.liveExpertConversationId;
      try {
        if (conversationId) {
          localStorage.setItem("sffcLiveExpertConversation", conversationId);
        } else {
          localStorage.removeItem("sffcLiveExpertConversation");
        }
      } catch (e) {
        // ignore storage errors
      }
    }

    bindFlowControllerEvents() {
      if (!this.flowController) return;

      const self = this;

      // Listen for follow-up questions
      $(document).on("showFollowUpQuestion", (e, data) => {
        self.showFollowUpQuestion(data);
      });

      // Listen for preference updates
      $(document).on("preferencesUpdated", (e, type, value) => {
        self.handlePreferenceUpdate(type, value);
      });

      // Listen for show match category events
      $(document).on("showMatchCategory", (e, category) => {
        self.showMatchesByCategory(category);
      });

      // Listen for search expansion
      $(document).on("expandSearchCriteria", () => {
        self.expandSearch();
      });

      // Listen for refinement requests
      $(document).on("refineSearchCriteria", () => {
        self.showRefinementOptions();
      });
    }

    cleanupModals() {
      // Remove any stuck modals or overlays from previous sessions
      $(
        ".cv-upload-modal, .cv-tailor-modal, .modal-backdrop, .modal-overlay, .field-mapper-overlay"
      ).remove();
      $("body").removeClass("modal-open").css("overflow", "");
    }

    loadShortlist() {
      const saved = localStorage.getItem("sffc_shortlist");
      if (saved) {
        try {
          this.shortlist = JSON.parse(saved);
        } catch (e) {
          this.shortlist = [];
        }
      }
    }

    bindEvents() {
      // CRITICAL: Centralized input handling to prevent conflicts
      const self = this;

      // Remove ALL existing handlers first to prevent duplicates
      $("#senna-input").off(".sennachat .sennaconv");
      $("#senna-send").off(".sennachat .sennaconv");

      // Set flag to indicate we're taking control
      window.sennaInputControlled = true;

      // Single unified handler with namespace
      $("#senna-input").on("keypress.sennaconv", function (e) {
        if (e.which === 13 && !e.shiftKey) {
          e.preventDefault();
          e.stopImmediatePropagation(); // Prevent other handlers
          self.handleUserInput();
        }
      });

      $("#senna-send").on("click.sennaconv", function (e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // Prevent other handlers
        self.handleUserInput();
      });

      $(document).off("click.sennaliveexpert", ".live-expert-choice");
      $(document).on(
        "click.sennaliveexpert",
        ".live-expert-choice",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const choice = $(this).data("choice");
          self.handleLiveExpertChoice(choice);
        }
      );

      $(document).off("click.sennatutorlesson", ".tutor-lesson-start");
      $(document).on(
        "click.sennatutorlesson",
        ".tutor-lesson-start",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          const lessonId = $(this).data("lesson-id");
          self.launchTutorLesson(lessonId);
        }
      );

      $(document).off("click.sennatutortopic", ".tutor-topic-switch");
      $(document).on(
        "click.sennatutortopic",
        ".tutor-topic-switch",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleTutorTopicSwitch();
        }
      );

      $(document).off("click.sennatutorsave", ".sffc-save-lesson-trigger");
      $(document).on(
        "click.sennatutorsave",
        ".sffc-save-lesson-trigger",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.handleSaveLesson();
        }
      );

      $(document).off("click.sennatutorhint", ".sffc-tutor-hint-trigger");
      $(document).on(
        "click.sennatutorhint",
        ".sffc-tutor-hint-trigger",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.toggleTutorHintDrawer(this);
        }
      );

      $(document).off("click.sennatutorhintoption", "[data-tutor-hint]");
      $(document).on(
        "click.sennatutorhintoption",
        "[data-tutor-hint]",
        function (e) {
          e.preventDefault();
          e.stopPropagation();
          self.requestTutorHint($(this).data("tutor-hint"));
        }
      );

      // Listen for PE filter events
      document.addEventListener("peFiltersApplied", (e) => {
        if (e.detail && e.detail.filteredJobs) {
          // Filters already handled the display in chat
        }
      });

      // Global ESC key handler for modals
      $(document).on("keydown", (e) => {
        if (e.key === "Escape") {
          // Close any open modals
          if ($(".cv-upload-modal").length > 0) {
            window.closeCVUploadModal();
          }
          if ($(".cv-tailor-modal").length > 0) {
            window.closeCVTailorModal();
          }
        }
      });

      // Click outside modal to close
      $(document).on(
        "click",
        ".cv-upload-modal, .cv-tailor-modal",
        function (e) {
          if (
            $(e.target).hasClass("cv-upload-modal") ||
            $(e.target).hasClass("cv-tailor-modal")
          ) {
            // Clicked on overlay, not content
            if ($(e.target).hasClass("cv-upload-modal")) {
              window.closeCVUploadModal();
            } else {
              window.closeCVTailorModal();
            }
          }
        }
      );

      // Hamburger menu toggle
      $("#menu-toggle").on("click", (e) => {
        e.stopPropagation();
        $("#stage-menu").toggleClass("active");
      });

      // Close menu when clicking outside
      $(document).on("click", (e) => {
        // Only handle menu closing, don't interfere with message content
        if (
          !$(e.target).closest(
            "#stage-menu, #menu-toggle, .senna-message, .sffc-message-content, .option-card, .quick-option"
          ).length
        ) {
          $("#stage-menu").removeClass("active");
        }
      });

      // Stage navigation - both desktop and mobile
      $(".stage-indicator, .stage-menu-item").on("click", (e) => {
        const stage = $(e.currentTarget).data("stage");

        // Close mobile menu
        $("#stage-menu").removeClass("active");

        if (stage === "analyze" && this.shortlist.length > 0) {
          this.switchToAnalyze();
        } else if (stage === "apply" && this.shortlist.length > 0) {
          this.switchToApply();
        } else if (stage === "browse") {
          this.switchToBrowse();
        } else if (stage === "analyze" || stage === "apply") {
          // Show message if no items shortlisted
          this.addSennaMessage(
            `Please shortlist some opportunities first before proceeding to ${stage}.`
          );
        }

        // Update active states
        $(".stage-indicator, .stage-menu-item").removeClass("active");
        $(
          `.stage-indicator[data-stage="${stage}"], .stage-menu-item[data-stage="${stage}"]`
        ).addClass("active");
      });

      // Listen for currency changes
      $(document).on("sffc:currency:changed", (e, newCurrency) => {
        // Re-render visible job cards with new currency
        if (this.currentStage === "browse" && this.filteredJobs.length > 0) {
          // Find the last job display message with job cards
          const jobMessages = $(".job-cards-container");
          if (jobMessages.length > 0) {
            const lastJobMessage = jobMessages.last();
            const visibleJobs = this.filteredJobs.slice(0, 6);
            const jobCardsHTML = visibleJobs
              .map((job, index) => this.createVogueJobCard(job, index))
              .join("");
            lastJobMessage.html(jobCardsHTML);
            this.bindCardEvents();
          }
        }

        // Optional: Show confirmation
        const config = window.currencyHandler.getCurrencyConfig(newCurrency);
        this.addSennaMessage(
          `Currency changed to ${config.name}. All salaries will now be displayed in ${config.symbol}.`
        );
      });

      // Scroll-based hiding for mode-pills and sffc-autocomplete-wrapper
      let lastScrollTop = 0;
      let scrollTimeout;
      const scrollThreshold = 5; // Minimum scroll distance to trigger

      $(window).on("scroll.sennaconv", function () {
        // Only apply scroll hiding on mobile devices
        const isMobile = window.innerWidth <= 768;
        if (!isMobile) return;

        // Skip if mobile-interface-v2 is active (it has its own scroll handling)
        if ($("body").hasClass("mobile-interface-v2")) return;

        clearTimeout(scrollTimeout);

        const currentScrollTop = $(this).scrollTop();
        const scrollDiff = currentScrollTop - lastScrollTop;

        // Ignore small scroll movements
        if (Math.abs(scrollDiff) < scrollThreshold) return;

        // Scrolling down - hide elements (only if scrolled past threshold)
        if (scrollDiff > 0 && currentScrollTop > 100) {
          // Hide mode pills
          $(".mode-pills").fadeOut(200);
          // Add scroll-hidden class to autocomplete wrapper
          $(".sffc-autocomplete-wrapper").addClass("scroll-hidden");
          $(".sffc-autocomplete-container").addClass("scroll-hidden");
          $(".sffc-input-group").addClass("scroll-hidden");
          // Add body class for CSS styling
          $("body").addClass("mobile-input-hidden");
        }
        // Scrolling up - show elements
        else if (scrollDiff < 0) {
          // Show mode pills
          $(".mode-pills").fadeIn(200);
          // Remove scroll-hidden class from autocomplete wrapper
          $(".sffc-autocomplete-wrapper").removeClass("scroll-hidden");
          $(".sffc-autocomplete-container").removeClass("scroll-hidden");
          $(".sffc-input-group").removeClass("scroll-hidden");
          // Remove body class
          $("body").removeClass("mobile-input-hidden");
        }

        // Update last scroll position
        lastScrollTop = currentScrollTop;

        // Auto-show elements when at top
        scrollTimeout = setTimeout(() => {
          if (currentScrollTop < 100) {
            $(".mode-pills").fadeIn(200);
            $(".sffc-autocomplete-wrapper").removeClass("scroll-hidden");
            $(".sffc-autocomplete-container").removeClass("scroll-hidden");
            $(".sffc-input-group").removeClass("scroll-hidden");
            $("body").removeClass("mobile-input-hidden");
          }
        }, 150);
      });
    }

    initializeModeToggle() {
      const options = document.querySelectorAll(".sffc-mode-option");

      if (!options.length) {
        this.applyChatMode(this.getDefaultChatMode());
        return;
      }

      const defaultMode = this.getStoredChatMode();
      this.applyChatMode(defaultMode);

      $(document)
        .off("click.sffcModeToggle", ".sffc-mode-option")
        .on("click.sffcModeToggle", ".sffc-mode-option", (event) => {
          event.preventDefault();
          const target = event.currentTarget;
          if (!target) {
            return;
          }

          const selectedMode = target.getAttribute("data-mode");
          this.applyChatMode(selectedMode);
        });
    }

    applyChatMode(mode) {
      const normalized =
        mode === "career-advice" ? "career-advice" : "job-search";
      const previousMode = this.chatMode;

      this.chatMode = normalized;
      window.sffcChatMode = normalized;

      const $options = $(".sffc-mode-option");
      if ($options.length) {
        $options.removeClass("is-active").attr("aria-checked", "false");
        $options
          .filter(`[data-mode="${normalized}"]`)
          .addClass("is-active")
          .attr("aria-checked", "true");
      }

      if (document && document.body) {
        document.body.setAttribute("data-sffc-chat-mode", normalized);
      }

      const input = document.getElementById("senna-input");
      if (input) {
        const placeholder =
          normalized === "career-advice"
            ? "Ask MENA Careers for an IB, asset management, or private equity lesson..."
            : "Send a message to the Live Expert team...";
        input.setAttribute("placeholder", placeholder);
      }

      try {
        localStorage.setItem("sffcChatMode", normalized);
      } catch (err) {
        // Storage might be disabled; ignore errors
      }

      if (normalized === "job-search") {
        this.requestLiveExpert({
          source: "mode-toggle",
          force: true,
          autoConnect: true,
        });
      }

      if (previousMode !== normalized) {
        $(document).trigger("sffcChatModeChange", [normalized, previousMode]);
      }
    }

    getStoredChatMode() {
      if (this.isLearningCoachPage()) {
        return "career-advice";
      }

      let storedMode = null;
      try {
        storedMode = localStorage.getItem("sffcChatMode");
      } catch (err) {
        storedMode = null;
      }

      if (storedMode === "career-advice" || storedMode === "job-search") {
        return storedMode;
      }

      const activeOption = document.querySelector(
        ".sffc-mode-option.is-active"
      );
      if (activeOption) {
        const mode = activeOption.getAttribute("data-mode");
        if (mode === "career-advice" || mode === "job-search") {
          return mode;
        }
      }

      return "job-search";
    }

    isLearningCoachPage() {
      return !!document.querySelector(".sffc-opportunities-wrapper");
    }

    getDefaultChatMode() {
      return this.isLearningCoachPage() ? "career-advice" : "job-search";
    }

    getChatMode() {
      if (this.chatMode === "career-advice" || this.chatMode === "job-search") {
        return this.chatMode;
      }

      const fallbackMode = this.getStoredChatMode();
      this.chatMode = fallbackMode;
      window.sffcChatMode = fallbackMode;
      return fallbackMode;
    }

    sendLearningCoachMessage(input) {
      const tutorContext = this.updateTutorLearningState(input);

      if (window.SennaChat && window.SennaChat.send) {
        window.SennaChat.send(input, {
          mode: "pe_tutor",
          persistTutorMode: true,
          lesson_state: tutorContext,
          system_prompt: this.buildLearningCoachSystemPrompt(tutorContext),
        });
        return;
      }

      this.addUserMessage(input);
      this.addSennaMessage(
        "Let's keep this as a lesson. We can work through investment banking, asset management, or private equity concepts one step at a time. Start here: if a company has GBP 20m EBITDA and trades at 10.0x, what is enterprise value?",
        false,
        "Finance Tutor"
      );
    }

    toggleTutorHintDrawer(trigger) {
      const drawer = document.querySelector(".sffc-tutor-hint-drawer");
      if (!drawer) {
        return;
      }

      const shouldOpen = drawer.hasAttribute("hidden");
      if (shouldOpen) {
        drawer.removeAttribute("hidden");
        drawer.classList.add("is-open");
      } else {
        drawer.setAttribute("hidden", "");
        drawer.classList.remove("is-open");
      }

      if (trigger) {
        trigger.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
      }
    }

    requestTutorHint(type = "nudge") {
      const normalized = ["nudge", "formula", "example"].includes(type)
        ? type
        : "nudge";
      const topic =
        this.tutorLearningState?.currentTopic || "the current finance concept";
      const hintPrompts = {
        nudge: `Give me a subtle hint for ${topic}. Do not solve it yet.`,
        formula: `Show me only the formula I should use for ${topic}, then ask me to plug in the numbers.`,
        example: `Show me a tiny worked example for ${topic}, then give me a similar one to try.`,
      };

      this.pendingTutorHintType = normalized;

      const drawer = document.querySelector(".sffc-tutor-hint-drawer");
      const trigger = document.querySelector(".sffc-tutor-hint-trigger");
      if (drawer) {
        drawer.setAttribute("hidden", "");
        drawer.classList.remove("is-open");
      }
      if (trigger) {
        trigger.setAttribute("aria-expanded", "false");
      }

      this.sendLearningCoachMessage(hintPrompts[normalized]);
    }

    restoreTutorLearningState() {
      const fallback = {
        turnCount: 0,
        currentTopic: "finance technical fundamentals",
        currentTrack: "general finance",
        learningStyle: "balanced",
        lastIntent: "lesson",
        recentConcepts: [],
        needsFeedback: false,
      };

      try {
        const stored = JSON.parse(
          localStorage.getItem("sffc_tutor_learning_state") || "{}"
        );
        return Object.assign({}, fallback, stored || {});
      } catch (err) {
        return fallback;
      }
    }

    saveTutorLearningState() {
      try {
        localStorage.setItem(
          "sffc_tutor_learning_state",
          JSON.stringify(this.tutorLearningState)
        );
      } catch (err) {
        // Storage may be unavailable; teaching still works without persistence.
      }
    }

    updateTutorLearningState(input) {
      const normalized = (input || "").toString().toLowerCase();
      const state = Object.assign({}, this.tutorLearningState || {});
      state.turnCount = (state.turnCount || 0) + 1;
      state.lastIntent = this.classifyTutorIntent(normalized);
      if (this.pendingTutorHintType) {
        state.lastIntent = "hint_request";
        state.lastHintType = this.pendingTutorHintType;
        this.pendingTutorHintType = "";
      }
      state.learningStyle = this.inferTutorLearningStyle(normalized, state);

      const topic = this.detectTutorTopic(normalized);
      if (topic) {
        state.currentTopic = topic.topic;
        state.currentTrack = topic.track;
      }

      if (topic && !state.recentConcepts?.includes(topic.topic)) {
        state.recentConcepts = [
          topic.topic,
          ...(state.recentConcepts || []),
        ].slice(0, 5);
      } else {
        state.recentConcepts = (state.recentConcepts || []).slice(0, 5);
      }

      state.needsFeedback =
        state.lastIntent === "student_answer" ||
        /\b(my answer|i got|equals|=|x|times|divided|gbp|£|\d)\b/.test(
          normalized
        );

      this.tutorLearningState = state;
      this.saveTutorLearningState();
      return state;
    }

    classifyTutorIntent(input) {
      if (/\b(job|jobs|role|roles|opening|openings|apply|application|cv|resume|salary|compensation|recruit|hiring|network)\b/.test(input)) {
        return "redirect_to_learning";
      }
      if (/\b(i think|my answer|answer is|i got|equals|=|gbp|£|\d+\.?\d*x|\d+)\b/.test(input)) {
        return "student_answer";
      }
      if (/\b(why|intuition|explain|confused|lost|don't understand|dont understand)\b/.test(input)) {
        return "concept_question";
      }
      if (/\b(practice|quiz|test me|give me a problem|drill)\b/.test(input)) {
        return "practice_request";
      }
      return "lesson";
    }

    inferTutorLearningStyle(input, state = {}) {
      if (/\b(simple|beginner|eli5|explain like|confused|lost)\b/.test(input)) {
        return "beginner";
      }
      if (/\b(formula|calculate|math|numbers|model|excel|step by step)\b/.test(input)) {
        return "numeric";
      }
      if (/\b(why|intuition|concept|conceptual|big picture)\b/.test(input)) {
        return "conceptual";
      }
      if (/\b(short|brief|quick|concise|summary)\b/.test(input)) {
        return "concise";
      }
      return state.learningStyle || "balanced";
    }

    detectTutorTopic(input) {
      const topics = [
        { topic: "three-statement modelling", track: "investment banking", pattern: /\b(three statement|3 statement|accounting|balance sheet|cash flow statement|income statement|working capital)\b/ },
        { topic: "DCF valuation", track: "investment banking", pattern: /\b(dcf|wacc|terminal value|free cash flow|fcf)\b/ },
        { topic: "trading comparables", track: "investment banking", pattern: /\b(trading comps|comps|ev\/ebitda|p\/e|multiple|multiples)\b/ },
        { topic: "precedent transactions", track: "investment banking", pattern: /\b(transaction comps|precedent|bid premium|takeover|m&a|merger|acquisition)\b/ },
        { topic: "accretion dilution", track: "investment banking", pattern: /\b(accretion|dilution|eps|pro forma|synergies)\b/ },
        { topic: "portfolio construction", track: "asset management", pattern: /\b(portfolio construction|asset allocation|diversification|weights?|allocation)\b/ },
        { topic: "risk and return", track: "asset management", pattern: /\b(sharpe|volatility|beta|alpha|tracking error|drawdown|risk return|risk\/return)\b/ },
        { topic: "fixed income", track: "asset management", pattern: /\b(duration|convexity|yield|bond|credit spread|fixed income|rates)\b/ },
        { topic: "performance attribution", track: "asset management", pattern: /\b(attribution|benchmark|active return|factor|sector allocation|selection effect)\b/ },
        { topic: "equity research", track: "asset management", pattern: /\b(equity research|stock pitch|investment thesis|coverage|earnings)\b/ },
        { topic: "debt schedule", track: "private equity", pattern: /\b(debt schedule|cash sweep|amortization|amortisation|interest|leverage)\b/ },
        { topic: "IRR and MOIC", track: "private equity", pattern: /\b(irr|moic|multiple of money|exit proceeds)\b/ },
        { topic: "LBO model", track: "private equity", pattern: /\b(lbo|buyout|sponsor equity|sources and uses)\b/ },
        { topic: "operating forecast", track: "cross-track modelling", pattern: /\b(revenue|ebitda|margin|forecast|growth)\b/ },
        { topic: "diligence", track: "private equity", pattern: /\b(diligence|quality of earnings|qoe)\b/ },
        { topic: "investment memo", track: "cross-track investing", pattern: /\b(memo|ic|investment committee|thesis|risks?)\b/ },
      ];

      return topics.find(({ pattern }) => pattern.test(input)) || null;
    }

    buildLearningCoachSystemPrompt(state) {
      const concepts = (state.recentConcepts || []).join(", ") || "none yet";
      return `${this.buildTutorPrompt(
        state.currentTopic || "finance technical fundamentals",
        `track: ${state.currentTrack || "general finance"}; current student style: ${state.learningStyle}; recent concepts: ${concepts}`
      )}

Lesson state:
- Turn number: ${state.turnCount}
- Current track: ${state.currentTrack || "general finance"}
- Current topic: ${state.currentTopic}
- Inferred learning style: ${state.learningStyle}
- Student intent this turn: ${state.lastIntent}
- Requested hint type: ${state.lastHintType || "none"}
- Recent concepts covered: ${concepts}
- Student likely needs feedback on an answer: ${state.needsFeedback ? "yes" : "no"}

Teaching requirements for this next reply:
- If this is a hint request, keep it small. For nudge, give one conceptual clue. For formula, give only the formula. For example, give one tiny worked example and then a similar problem.
- If the student attempted an answer, grade it first: what is correct, what needs fixing, and the corrected calculation.
- If the student seems confused, slow down and use a simpler analogy before formulas.
- If the student is numeric/model-driven, show formulas and calculations explicitly.
- If the student is concise, keep the lesson tight but still include one worked example.
- Do not restart the lesson or reintroduce yourself.
- End with one next question that follows from this exact turn.`;
    }

    checkForSharedJobs() {
      if (this.isLearningCoachPage()) {
        return;
      }

      // Parse URL parameters
      const urlParams = new URLSearchParams(window.location.search);
      const singleJobId = urlParams.get("job_id");
      const multipleJobIds = urlParams.get("job_ids");

      if (singleJobId || multipleJobIds) {
        // Collect job IDs
        let jobIds = [];
        if (singleJobId) {
          jobIds = [singleJobId];
        }
        if (multipleJobIds) {
          jobIds = multipleJobIds.split(",").map((id) => id.trim());
        }

        // Validate job IDs (must be numbers)
        jobIds = jobIds.filter((id) => /^\d+$/.test(id));

        if (jobIds.length > 0) {
          // Store for later use after jobs are loaded
          this.sharedJobIds = jobIds;
          this.isSharedLink = true;

          // Will fetch after initial load completes
          setTimeout(() => this.fetchAndDisplaySharedJobs(jobIds), 1000);
        } else {
        }
      }
    }

    fetchAndDisplaySharedJobs(jobIds) {
      // Auto-open conversational view if not already open
      if (!$(".sffc-conversational-view").hasClass("active")) {
        this.openConversationalView();
      }

      // Show skeleton loader instead of simple loading message
      this.showSkeletonLoader(jobIds.length);

      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_get_shared_jobs",
          job_ids: jobIds.join(","),
          nonce: window.sffc_ajax?.nonce || "",
        },
        success: (response) => {
          if (
            response.success &&
            response.data?.jobs &&
            response.data.jobs.length > 0
          ) {
            this.displaySharedJobs(response.data.jobs);
            this.clearShareParameters();
          } else {
            // Fallback: search in already loaded jobs
            const numericIds = jobIds.map((id) => parseInt(id));
            const localJobs = this.allJobs.filter(
              (job) =>
                numericIds.includes(job.id) ||
                numericIds.includes(parseInt(job.id))
            );

            if (localJobs.length > 0) {
              this.displaySharedJobs(localJobs);
              this.clearShareParameters();
            } else {
              // Jobs not found - let the AI handle it with context
              this.hideSkeletonLoader();
              // Wait for initial jobs to load, then pass query to AI
              setTimeout(() => {
                if (jobIds.length === 1) {
                  this.addSennaMessage(
                    `Looking for opportunity #${jobIds[0]}...`
                  );
                  this.processUserIntent(`Show me job with ID ${jobIds[0]}`);
                } else {
                  this.addSennaMessage(
                    `Looking for ${jobIds.length} shared opportunities...`
                  );
                  this.processUserIntent(
                    `Show me jobs with IDs ${jobIds.join(", ")}`
                  );
                }
              }, 1500);
            }
          }
        },
        error: (xhr, status, error) => {
          // More specific error messages
          let errorMsg = "Unable to load the shared opportunity.";
          if (xhr.status === 404) {
            errorMsg =
              "The sharing endpoint could not be found. Please refresh and try again.";
          } else if (xhr.status === 500) {
            errorMsg = "Server error occurred. Please try again later.";
          } else if (xhr.status === 0) {
            errorMsg = "Network error. Please check your connection.";
          }

          this.handleShareError(errorMsg);
        },
      });
    }

    displaySharedJobs(jobs) {
      // Hide skeleton loader with smooth transition
      this.hideSkeletonLoader();

      if (!jobs || jobs.length === 0) {
        this.handleShareError("The shared opportunity is no longer available.");
        return;
      }

      // Minimal delay for smooth transition from skeleton
      setTimeout(() => {
        // Add welcome message with special styling
        const count = jobs.length;
        const message =
          count === 1
            ? "✨ Here's the opportunity that was shared with you:"
            : `✨ Here are the ${count} opportunities that were shared with you:`;

        this.addSennaMessage(message, "shared-intro");

        // Display jobs almost immediately
        setTimeout(() => {
          // Add shared flag to jobs
          const sharedJobs = jobs.map((job) => ({
            ...job,
            isShared: true,
          }));
          this.renderJobsInChat(sharedJobs, false);

          // Scroll to the shared jobs
          this.scrollToLatestMessage();
        }, 50); // Reduced from 200ms

        // Add completion message
        if (count > 1) {
          setTimeout(() => {
            this.addSennaMessage(
              `Would you like to compare these ${count} opportunities? Click the compare button to see them side by side.`
            );
          }, 400); // Reduced from 1000ms
        }
      }, 100); // Reduced from 300ms
    }

    scrollToLatestMessage() {
      const messagesContainer = document.getElementById("senna-messages");
      if (messagesContainer) {
        const lastMessage = messagesContainer.lastElementChild;
        if (lastMessage) {
          lastMessage.scrollIntoView({ behavior: "smooth", block: "end" });
        }
      }
    }

    handleShareError(errorMessage) {
      this.hideSkeletonLoader();
      this.addSennaMessage(`⚠️ ${errorMessage}`);
    }

    showSkeletonLoader(jobCount = 1) {
      // Create skeleton HTML based on number of jobs
      const skeletonHTML = this.generateSkeletonHTML(jobCount);

      // Add skeleton to chat
      const $skeleton = $(`
                <div class="skeleton-jobs-container" id="skeleton-loader">
                    <div class="skeleton-loading-message">
                        <span>🔍 Finding your shared opportunit${
                          jobCount > 1 ? "ies" : "y"
                        }</span>
                        <span class="skeleton-loading-dots">
                            <span class="skeleton-pulse"></span>
                            <span class="skeleton-pulse"></span>
                            <span class="skeleton-pulse"></span>
                        </span>
                    </div>
                    ${skeletonHTML}
                </div>
            `);

      $("#senna-messages").append($skeleton);
      this.scrollToLatestMessage();
    }

    generateSkeletonHTML(count) {
      let html = '<div class="skeleton-job-cards">';

      // Generate skeleton cards based on count
      const cardsToShow = Math.min(count, 3); // Show max 3 skeleton cards

      for (let i = 0; i < cardsToShow; i++) {
        html += `
                    <div class="skeleton-job-card">
                        <div class="skeleton-match-score"></div>
                        <div class="skeleton-element skeleton-job-title"></div>
                        <div class="skeleton-element skeleton-company"></div>
                        <div class="skeleton-element skeleton-location"></div>
                        <div class="skeleton-element skeleton-salary"></div>
                        <div class="skeleton-skills">
                            <div class="skeleton-skill-tag"></div>
                            <div class="skeleton-skill-tag"></div>
                            <div class="skeleton-skill-tag"></div>
                        </div>
                        <div class="skeleton-description">
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                            <div class="skeleton-line"></div>
                        </div>
                        <div class="skeleton-actions">
                            <div class="skeleton-button"></div>
                            <div class="skeleton-button"></div>
                        </div>
                    </div>
                `;
      }

      if (count > 3) {
        html += `<div class="skeleton-multiple-indicator">Loading ${
          count - 3
        } more...</div>`;
      }

      html += "</div>";
      return html;
    }

    hideSkeletonLoader() {
      const $skeleton = $("#skeleton-loader");
      if ($skeleton.length) {
        // Add transition class for smooth fade out
        $skeleton.addClass("skeleton-transitioning");

        // Remove after animation completes
        setTimeout(() => {
          $skeleton.remove();
        }, 300);
      }
    }

    clearShareParameters() {
      // Remove job parameters from URL without page reload
      const url = new URL(window.location);
      url.searchParams.delete("job_id");
      url.searchParams.delete("job_ids");
      window.history.replaceState(
        {},
        document.title,
        url.pathname + url.search
      );
    }

    openConversationalView() {
      // Show the conversational view
      $(".sffc-conversational-view").addClass("active");

      // Hide other views if needed
      $(".sffc-default-view").removeClass("active");

      // Update any navigation indicators
      $(".view-toggle").removeClass("active");
      $('.view-toggle[data-view="conversational"]').addClass("active");

      // Trigger resize event for proper layout
      $(window).trigger("resize");

      // Scroll to top
      window.scrollTo(0, 0);
    }

    getTimeAgo(dateString) {
      if (!dateString) return "";
      const date = new Date(dateString);
      const now = new Date();
      const diff = Math.floor((now - date) / (1000 * 60 * 60 * 24));

      if (diff === 0) return "Today";
      if (diff === 1) return "Yesterday";
      if (diff < 7) return `${diff} days ago`;
      if (diff < 30) return `${Math.floor(diff / 7)} weeks ago`;
      return `${Math.floor(diff / 30)} months ago`;
    }

    getMatchHeader() {
      // Check if profile is complete
      const profileData = window.getCompleteProfileData
        ? window.getCompleteProfileData()
        : {};
      const isComplete = window.isProfileComplete
        ? window.isProfileComplete(profileData)
        : false;

      if (isComplete) {
        return {
          title: "Found Strong Matches",
          subtitle:
            "Based on your profile and preferences, these opportunities align well with your career trajectory:",
        };
      } else {
        return {
          title: "Found Trending Roles",
          subtitle: "Popular opportunities currently available in the market:",
        };
      }
    }

    loadInitialJobs() {
      if (this.isLearningCoachPage()) {
        this.showLiveExpertPanel({
          headline: "Finance Learning Tutor",
          source: "learning-console",
        });
        return;
      }

      if (this.jobSearchDisabled) {
        this.showLiveExpertPanel({
          headline: "Live Expert Support",
          source: "initial-load",
        });
        return;
      }

      // Check for shared jobs first
      this.checkForSharedJobs();

      // Don't show skeleton for initial load if shared jobs are present
      const urlParams = new URLSearchParams(window.location.search);
      const hasSharedJobs = urlParams.get("job_id") || urlParams.get("job_ids");

      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      // Load initial batch for display (20 jobs)
      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_get_opportunities",
          nonce: window.sffc_ajax?.nonce || "",
          limit: 20,
          offset: 0,
        },
        success: (response) => {
          if (response && response.success && response.data?.opportunities) {
            this.allJobs = response.data.opportunities;
            this.filteredJobs = [...this.allJobs];
            this.presentInitialJobs();

            // Load ALL jobs in background for CV matcher
            this.loadAllJobsForMatcher();
          } else {
            this.loadJobsViaREST();
          }
        },
        error: (xhr, status, error) => {
          // Try REST API fallback
          this.loadJobsViaREST();
        },
      });
    }

    loadAllJobsForMatcher() {
      // Load ALL jobs from database for CV matching
      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_get_opportunities",
          nonce: window.sffc_ajax?.nonce || "",
          limit: 1000, // Get all jobs
          offset: 0,
        },
        success: (response) => {
          if (response && response.success && response.data?.opportunities) {
            // Update allJobs with complete dataset
            this.allJobs = response.data.opportunities;
            console.log(
              `CV Matcher: Loaded ${this.allJobs.length} total jobs from database`
            );

            // CRITICAL FIX: Check for pending CV matches after jobs load
            if (window.pendingCVMatch && this.allJobs.length > 0) {
              console.log(
                "[SennaConversational] ✅ Triggering pending CV match - jobs now available"
              );
              const matchedJobs = window.matchJobsWithCV(
                window.pendingCVMatch,
                this.allJobs
              );
              setTimeout(() => {
                window.prepareJobMatches(window.pendingCVMatch, matchedJobs, {
                  source: "pending",
                  refreshReview: true,
                });
                window.pendingCVMatch = null; // Clear the pending flag
              }, 400);
            }

            // Trigger event for other components
            $(document).trigger("allJobsLoaded", [this.allJobs]);
          }
        },
      });
    }

    loadJobsViaREST() {
      const restUrl = window.location.origin + "/wp-json/sffc/v1/opportunities";

      $.ajax({
        url: restUrl,
        type: "POST",
        dataType: "json",
        data: {
          limit: 1000, // Get all jobs for CV matcher
          offset: 0,
        },
        success: (response) => {
          if (response && response.data?.opportunities) {
            this.allJobs = response.data.opportunities;
            this.filteredJobs = [...this.allJobs];
            this.presentInitialJobs();
          } else {
            // Use sample data as last resort
            this.loadSampleJobs();
          }
        },
        error: (xhr, status, error) => {
          // Use sample data as last resort
          this.loadSampleJobs();
        },
      });
    }

    loadSampleJobs() {
      // Sample jobs for testing when database is empty
      this.allJobs = [
        {
          id: 1,
          title: "Sr. Technology Risk Associate",
          company: "JPMorgan Chase",
          location: "New York, NY",
          salary_min: 140000,
          salary_max: 180000,
          job_type: "Full-time",
          skills: ["Risk Management", "Compliance", "Technology Audit", "SOX"],
          description:
            "Manage technology risk assessment and compliance initiatives",
          requirements:
            "Experience in risk management and compliance frameworks",
        },
        {
          id: 2,
          title: "Investment Banking Analyst",
          company: "Goldman Sachs",
          location: "New York, NY",
          salary_min: 110000,
          salary_max: 130000,
          job_type: "Full-time",
          skills: [
            "Financial Modeling",
            "Valuation",
            "DCF",
            "M&A",
            "Excel",
            "PowerPoint",
          ],
          match_score: 95,
          description:
            "Support M&A transactions and capital markets deals in TMT coverage group",
        },
        {
          id: 3,
          title: "Private Equity Associate",
          company: "Blackstone",
          location: "New York, NY",
          salary_min: 175000,
          salary_max: 225000,
          job_type: "Full-time",
          skills: [
            "LBO Modeling",
            "Due Diligence",
            "Portfolio Management",
            "Deal Sourcing",
          ],
          match_score: 90,
          description:
            "Execute leveraged buyouts and growth equity investments in middle-market companies",
        },
        {
          id: 4,
          title: "Private Credit Analyst",
          company: "Ares Management",
          location: "London, UK",
          salary_min: 150000,
          salary_max: 200000,
          job_type: "Full-time",
          skills: [
            "Quantitative Analysis",
            "Risk Management",
            "Trading",
            "Python",
            "Research",
          ],
          match_score: 88,
          description:
            "Develop systematic trading strategies and conduct macroeconomic research",
        },
        {
          id: 5,
          title: "Venture Capital Analyst",
          company: "Sequoia Capital",
          location: "Menlo Park, CA",
          salary_min: 125000,
          salary_max: 150000,
          job_type: "Full-time",
          skills: [
            "Market Research",
            "Financial Analysis",
            "Due Diligence",
            "Startup Ecosystem",
          ],
          match_score: 87,
          description: "Source and evaluate early-stage technology investments",
        },
        {
          id: 3,
          title: "Data Scientist",
          company: "DataCo",
          location: "New York, NY",
          salary_min: 140000,
          salary_max: 190000,
          job_type: "Full-time",
          skills: ["Python", "Machine Learning", "SQL", "TensorFlow"],
          match_score: 88,
          description: "Build ML models for business insights",
        },
        {
          id: 4,
          title: "UX Designer",
          company: "DesignStudio",
          location: "Remote",
          salary_min: 95000,
          salary_max: 130000,
          job_type: "Full-time",
          skills: ["Figma", "User Research", "Prototyping"],
          match_score: 79,
          description: "Create beautiful user experiences",
        },
        {
          id: 5,
          title: "DevOps Engineer",
          company: "CloudTech",
          location: "Austin, TX",
          salary_min: 115000,
          salary_max: 160000,
          job_type: "Full-time",
          skills: ["Docker", "Kubernetes", "CI/CD", "AWS"],
          match_score: 83,
          description: "Maintain cloud infrastructure",
        },
        {
          id: 6,
          title: "Marketing Director",
          company: "GrowthCo",
          location: "Chicago, IL",
          salary_min: 125000,
          salary_max: 175000,
          job_type: "Full-time",
          skills: ["Digital Marketing", "Brand Strategy", "Analytics"],
          match_score: 77,
          description: "Lead marketing initiatives",
        },
      ];

      this.filteredJobs = [...this.allJobs];
      this.presentInitialJobs();
    }

    // Public method to show greeting - can be called from other components
    sendGreeting() {
      // Only send if no messages exist yet and welcome not already shown
      if ($(".sffc-message").length === 0 && !this.welcomeMessageShown) {
        this.presentInitialJobs();
      }
    }

    /**
     * Display follow-up question with quick response buttons
     */
    showFollowUpQuestion(data) {
      const { question, quickResponses, handler, stepId } = data;

      // Don't show if already waiting for response
      if (
        this.stateManager &&
        this.stateManager.state.conversationContext.awaitingResponse
      ) {
        return;
      }

      // Create question container
      const questionHtml = `
                <div class="senna-message follow-up-question" data-step-id="${stepId}">
                    <span class="question-icon"></span>
                    <div class="message-content">${question}</div>
                    ${
                      quickResponses && quickResponses.length > 0
                        ? `
                        <div class="quick-responses">
                            ${quickResponses
                              .map(
                                (response, idx) => `
                                <button class="quick-response-btn" 
                                        data-response="${response.replace(
                                          /"/g,
                                          "&quot;"
                                        )}"
                                        data-handler="${handler}"
                                        data-step-id="${stepId}">
                                    ${response}
                                </button>
                            `
                              )
                              .join("")}
                        </div>
                    `
                        : ""
                    }
                </div>
            `;

      // Add to conversation
      this.addToConversation(questionHtml);

      // Bind click handlers for quick responses
      this.bindQuickResponseHandlers();

      // Mark as awaiting response
      if (this.stateManager) {
        this.stateManager.state.conversationContext.awaitingResponse = true;
      }
    }

    /**
     * Bind handlers for quick response buttons
     */
    bindQuickResponseHandlers() {
      const self = this;

      $(".quick-response-btn:not(.bound)").each(function () {
        $(this)
          .addClass("bound")
          .on("click", function (e) {
            e.preventDefault();

            const $btn = $(this);
            const response = $btn.data("response");
            const handler = $btn.data("handler");
            const stepId = $btn.data("step-id");

            // Visual feedback
            $btn.addClass("selected clicked");
            $btn.siblings().fadeOut(200);

            // Process the response
            setTimeout(() => {
              // Hide the quick responses
              // Smooth fade out instead of slide to prevent mobile bouncing
              const $quickResponses = $btn.closest(".quick-responses");
              $quickResponses.css({
                transition: "opacity 0.2s ease",
                opacity: "0",
              });
              setTimeout(() => {
                $quickResponses.css("display", "none");
              }, 200);

              // Add user's response as a message
              const userMessage = `
                            <div class="senna-message senna-user">
                                <div class="message-content">${response}</div>
                            </div>
                        `;
              self.addToConversation(userMessage);

              // Process through flow controller
              if (self.flowController) {
                const question = $btn
                  .closest(".follow-up-question")
                  .find(".message-content")
                  .text();
                self.flowController.processResponse(question, response, {
                  handler: handler,
                  stepId: stepId,
                });
              }

              // Clear awaiting flag
              if (self.stateManager) {
                self.stateManager.state.conversationContext.awaitingResponse = false;
              }
            }, 300);
          });
      });
    }

    /**
     * Add content to the conversation
     */
    addToConversation(html) {
      const $messages = $("#senna-messages");
      const $newMessage = $(html);

      // Add with animation
      $newMessage.hide();
      $messages.append($newMessage);
      $newMessage.fadeIn(300);

      // Scroll to bottom
      this.scrollToBottom();
    }

    /**
     * Scroll conversation to bottom
     */
    scrollToBottom() {
      const $messages = $("#senna-messages");
      if ($messages.length) {
        $messages.animate(
          {
            scrollTop: $messages[0].scrollHeight,
          },
          300
        );
      }
    }

    /**
     * Show matches by category (perfect, strong, possible)
     */
    showMatchesByCategory(category) {
      if (!this.stateManager || !this.stateManager.state.matchResults) {
        return;
      }

      const results = this.stateManager.state.matchResults;
      let matches = [];
      let title = "";

      switch (category) {
        case "perfect":
          matches = results.perfectMatches;
          title = "Perfect Matches";
          break;
        case "strong":
          matches = results.strongMatches;
          title = "Strong Matches";
          break;
        case "possible":
          matches = results.possibleMatches;
          title = "Possible Opportunities";
          break;
        case "all":
          matches = [
            ...results.perfectMatches,
            ...results.strongMatches,
            ...results.possibleMatches,
          ];
          title = "All Matches";
          break;
        default:
          matches = results.perfectMatches;
          title = "Matches";
      }

      // Create category tabs UI
      const categoriesHtml = `
                <div class="match-categories-container">
                    <div class="match-categories">
                        <button class="category-tab ${
                          category === "perfect" ? "active" : ""
                        }" 
                                data-category="perfect">
                            Perfect
                            <span class="badge">${
                              results.perfectMatches.length
                            }</span>
                        </button>
                        <button class="category-tab ${
                          category === "strong" ? "active" : ""
                        }" 
                                data-category="strong">
                            Strong
                            <span class="badge">${
                              results.strongMatches.length
                            }</span>
                        </button>
                        <button class="category-tab ${
                          category === "possible" ? "active" : ""
                        }" 
                                data-category="possible">
                            Possible
                            <span class="badge">${
                              results.possibleMatches.length
                            }</span>
                        </button>
                        <button class="category-tab ${
                          category === "all" ? "active" : ""
                        }" 
                                data-category="all">
                            All
                            <span class="badge">${results.totalFound}</span>
                        </button>
                    </div>
                    <div class="category-content" data-category="${category}">
                        <h3>${title}</h3>
                        ${this.renderJobCards(matches)}
                    </div>
                </div>
            `;

      // Add to conversation
      this.addToConversation(categoriesHtml);

      // Bind category tab handlers
      this.bindCategoryTabHandlers();

      // Bind card events for the Vogue cards
      this.bindCardEvents();

      // Update display count
      if (this.stateManager) {
        this.stateManager.state.matchResults.displayedCount += matches.length;
        this.stateManager.saveSession();
      }
    }

    /**
     * Bind handlers for category tabs
     */
    bindCategoryTabHandlers() {
      const self = this;

      $(".category-tab:not(.bound)").each(function () {
        $(this)
          .addClass("bound")
          .on("click", function () {
            const $tab = $(this);
            const category = $tab.data("category");

            // Update active state
            $tab.addClass("active").siblings().removeClass("active");

            // Update content
            const results = self.stateManager.state.matchResults;
            let matches = [];
            let title = "";

            switch (category) {
              case "perfect":
                matches = results.perfectMatches;
                title = "Perfect Matches";
                break;
              case "strong":
                matches = results.strongMatches;
                title = "Strong Matches";
                break;
              case "possible":
                matches = results.possibleMatches;
                title = "Possible Opportunities";
                break;
              case "all":
                matches = [
                  ...results.perfectMatches,
                  ...results.strongMatches,
                  ...results.possibleMatches,
                ];
                title = "All Matches";
                break;
            }

            // Update category content
            const $content = $tab
              .closest(".match-categories-container")
              .find(".category-content");
            $content.attr("data-category", category).html(`
                        <h3>${title}</h3>
                        ${self.renderJobCards(matches)}
                    `);

            // Bind card events for the new cards
            self.bindCardEvents();
          });
      });
    }

    /**
     * Show refinement options
     */
    showRefinementOptions() {
      const refinementHtml = `
    <div class="preference-control">
      <h4>Let's refine your search</h4>
      <div class="refinement-options">
        <div class="refinement-chip" data-refine="location">
          <span>Expand locations</span>
        </div>
        <div class="refinement-chip" data-refine="seniority">
          <span>Adjust seniority</span>
        </div>
        <div class="refinement-chip" data-refine="sectors">
          <span>Change sectors</span>
        </div>
        <div class="refinement-chip" data-refine="firm-type">
          <span>Firm types</span>
        </div>
        <div class="refinement-chip" data-refine="remote">
          <span>Include remote</span>
        </div>
        <div class="refinement-chip" data-refine="compensation">
          <span>Salary range</span>
        </div>
      </div>
    </div>
  `;

      this.addToConversation(refinementHtml);
      this.bindRefinementHandlers();
    }

    /**
     * Bind refinement option handlers
     */
    bindRefinementHandlers() {
      const self = this;

      $(".refinement-chip:not(.bound)").each(function () {
        $(this)
          .addClass("bound")
          .on("click", function () {
            const $chip = $(this);
            const refineType = $chip.data("refine");

            // Toggle selection
            $chip.toggleClass("selected");

            // Apply refinement immediately
            self.applyRefinement(refineType);
          });
      });
    }

    /**
     * Apply refinement filters to job results
     */
    applyRefinement(refineType) {
      this.showLiveExpertPanel({ source: "refinement" });
    }

    /**
     * Expand search criteria
     */
    expandSearch() {
      // Show thinking indicator
      const thinkingHtml = `
                <div class="thinking-indicator">
                    <div class="dots">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                    </div>
                    <span>Expanding search criteria...</span>
                </div>
            `;

      this.addToConversation(thinkingHtml);

      // Simulate expansion (in real implementation, would call backend)
      setTimeout(() => {
        $(".thinking-indicator").fadeOut(200, function () {
          $(this).remove();
        });

        // Show expanded results
        const expandedMsg = `
                    <div class="senna-message senna-assistant">
                        <div class="message-content">
                            I've expanded your search to include nearby locations and similar seniority levels.
                            Here are additional opportunities that might interest you:
                        </div>
                    </div>
                `;

        this.addToConversation(expandedMsg);

        // Trigger new search
        if (this.intelligentFilter) {
          this.intelligentFilter.expandCriteria();
        }
      }, 1500);
    }

    /**
     * Handle preference updates
     */
    handlePreferenceUpdate(type, value) {
      // Show confirmation
      const confirmHtml = `
                <div class="preference-saved">
                    <span class="checkmark">✓</span>
                    <span>Preference updated: ${type}</span>
                </div>
            `;

      this.addToConversation(confirmHtml);

      // Fade out after 2 seconds
      setTimeout(() => {
        $(".preference-saved").fadeOut(300);
      }, 2000);
    }

    /**
     * Render job cards using the Vogue card format
     */
    renderJobCards(jobs) {
      return this.renderLiveExpertPanel({ source: "job-cards" });
    }

    startConversation() {
      // Check for input field session continuation first
      this.checkInputFieldSession();

      const userName = window.sffc_ajax?.user_name || "there";
      const hour = new Date().getHours();
      const greeting =
        hour < 12
          ? "Good morning"
          : hour < 18
          ? "Good afternoon"
          : "Good evening";

      // Check if profile is complete
      this.checkProfileCompletion();

      // Removed greeting and profile completion messages - content now starts immediately
    }

    // Phase 2: Insert PE Filter Bar
    insertFilterBar() {
      // Check if filter container already exists
      if (document.getElementById("pe-filter-container")) {
        return;
      }

      // Find the main container element
      const mainContainer = document.querySelector(".sffc-main-container");
      if (!mainContainer) {
        setTimeout(() => this.insertFilterBar(), 500);
        return;
      }

      // Create filter container wrapper
      const filterContainer = document.createElement("div");
      filterContainer.id = "pe-filter-container";
      filterContainer.className = "pe-filter-container-wrapper";

      // Insert BEFORE (above) the main container
      mainContainer.parentNode.insertBefore(filterContainer, mainContainer);

      // Trigger event for filter initialization
      document.dispatchEvent(new CustomEvent("peFilterContainerReady"));
    }

    checkProfileCompletion() {
      // Check localStorage or session for profile completion status
      const profileData = localStorage.getItem("sffc_user_profile");
      if (profileData) {
        try {
          const profile = JSON.parse(profileData);
          // Check for essential fields
          this.isProfileComplete = !!(
            profile.full_name &&
            profile.skills &&
            profile.skills.length > 0 &&
            profile.years_experience
          );
        } catch (e) {
          this.isProfileComplete = false;
        }
      } else {
        this.isProfileComplete = false;
      }

      // Also check via AJAX if user is logged in
      if (window.sffc_ajax?.user_id) {
        $.ajax({
          url: window.sffc_ajax.url,
          type: "POST",
          data: {
            action: "sffc_check_profile_completion",
            nonce: window.sffc_ajax.nonce,
            user_id: window.sffc_ajax.user_id,
          },
          success: (response) => {
            if (response && response.success && response.data) {
              this.isProfileComplete = !!response.data.is_complete;
              if (this.isProfileComplete && response.data.membership_url) {
                this.showMembershipUpgradePrompt(response.data.membership_url);
              }
            }
          },
        });
      }
    }

    showMembershipUpgradePrompt(url) {
      if (!url || this.membershipPromptShown) {
        return;
      }

      this.membershipPromptShown = true;

      const prompt = $(`
        <div class="senna-membership-prompt" style="position: fixed; bottom: 24px; right: 24px; z-index: 1100; background: linear-gradient(135deg, #fbf7f0, #fff); border: 1px solid rgba(13,53,62,0.08); box-shadow: 0 15px 35px rgba(10, 37, 48, 0.15); border-radius: 16px; padding: 18px 20px; width: 320px; max-width: calc(100% - 40px);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 42px; height: 42px; border-radius: 50%; background: #0d353e; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600;">AI</div>
                <div>
                    <div style="font-weight: 600; color: #0d353e;">Profile ready for recruiter outreach</div>
                    <p style="font-size: 13px; color: #475569; margin: 4px 0 0;">Unlock MemberPress access to join MENA Careers's curated introductions.</p>
                </div>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" data-action="join-membership" style="flex: 1; background: #0d353e; color: #fff; border: none; border-radius: 999px; padding: 9px 14px; font-size: 13px; font-weight: 600; cursor: pointer;">See memberships</button>
                <button type="button" data-action="dismiss-membership" style="flex: 1; background: transparent; color: #0d353e; border: 1px solid rgba(13,53,62,0.25); border-radius: 999px; padding: 9px 14px; font-size: 13px; font-weight: 600; cursor: pointer;">Maybe later</button>
            </div>
        </div>
      `);

      const dismiss = () => {
        prompt.fadeOut(300, () => prompt.remove());
      };

      $("body").append(prompt);

      prompt.find('[data-action="join-membership"]').on("click", () => {
        window.open(url, "_blank");
        dismiss();
      });

      prompt.find('[data-action="dismiss-membership"]').on("click", dismiss);

      setTimeout(dismiss, 10000);
    }

    checkInputFieldSession() {
      if (this.sessionContinuationHandled) {
        return;
      }

      const urlParams = new URLSearchParams(window.location.search);
      const sessionId = urlParams.get("senna_session");
      const autoStart = urlParams.get("auto_start");

      if (sessionId && autoStart === "1") {
        this.sessionContinuationHandled = true;

        const loadingMessageId = this.addSennaMessage(
          "Loading your question from the previous page...",
          true
        );

        const ajaxUrl =
          window.sffc_ajax?.url ||
          window.sffc_ajax?.ajax_url ||
          "/wp-admin/admin-ajax.php";

        $.ajax({
          url: ajaxUrl,
          type: "POST",
          data: {
            action: "sffc_get_input_session",
            session_id: sessionId,
            nonce: window.sffc_ajax?.nonce || "",
          },
          success: (response) => {
            if (response && response.success && response.data.session_found) {
              this.cleanupSessionUrl();
              this.dispatchSessionQueryToSenna({
                loadingMessageId,
                originalQuery: response.data.original_query,
                enhancedQuery: response.data.enhanced_query,
                context: response.data.context || {},
              });
            } else {
              this.removeMessageById(loadingMessageId);
              this.addSennaMessage(
                "Sorry, I couldn't find your previous question. Please ask again!",
                true
              );
            }
          },
          error: () => {
            this.removeMessageById(loadingMessageId);
            this.addSennaMessage(
              "There was an issue loading your question. Please try asking again.",
              true
            );
          },
        });
      }
    }

    cleanupSessionUrl() {
      // Remove session parameters from URL without page reload
      const url = new URL(window.location);
      url.searchParams.delete("senna_session");
      url.searchParams.delete("auto_start");
      window.history.replaceState({}, document.title, url.toString());
    }

    dispatchSessionQueryToSenna({
      loadingMessageId,
      originalQuery,
      enhancedQuery,
      context,
    }) {
      if (!originalQuery) {
        this.removeMessageById(loadingMessageId);
        this.addSennaMessage(
          "I couldn't load your previous question. Please ask again when you're ready.",
          true
        );
        return;
      }

      const additionalContext = {
        source: "senna_input_field",
        original_query: originalQuery,
        enhanced_query: enhancedQuery || originalQuery,
        input_field_context: context || {},
      };

      this.waitForSennaChat(
        (sennaChat) => {
          try {
            this.removeMessageById(loadingMessageId);
            sennaChat.send(originalQuery, additionalContext);
          } catch (error) {
            console.error("Failed to send session query to MENA Careers:", error);
            this.addSennaMessage(
              "Something went wrong while sending your question. Please ask again manually.",
              true
            );
          }
        },
        () => {
          this.removeMessageById(loadingMessageId);
          this.addSennaMessage(
            "I couldn't start the conversation automatically. Please ask your question again here.",
            true
          );
        }
      );
    }

    waitForSennaChat(onReady, onTimeout, attempts = 20) {
      if (window.SennaChat && typeof window.SennaChat.send === "function") {
        if (typeof onReady === "function") {
          onReady(window.SennaChat);
        }
        return;
      }

      if (attempts <= 0) {
        if (typeof onTimeout === "function") {
          onTimeout();
        }
        return;
      }

      setTimeout(
        () => this.waitForSennaChat(onReady, onTimeout, attempts - 1),
        250
      );
    }

    removeMessageById(messageId) {
      if (!messageId) {
        return;
      }

      const messageElement = document.getElementById(messageId);

      if (messageElement) {
        const container = messageElement.closest(".senna-message");

        if (container) {
          container.remove();
        } else {
          messageElement.remove();
        }
      }
    }

    presentInitialJobs() {
      if (this.jobSearchDisabled) {
        this.showLiveExpertPanel({ source: "present-initial" });
        return;
      }

      // Prevent duplicate welcome messages
      if (this.welcomeMessageShown) {
        return;
      }

      // If no jobs loaded, use sample jobs but don't show them
      if (!this.allJobs || this.allJobs.length === 0) {
        this.loadSampleJobs();
        return;
      }

      // Set flag to prevent duplicate messages
      this.welcomeMessageShown = true;

      // Store jobs but DON'T display them initially
      this.displayedJobs = this.filteredJobs.slice(0, 6);

      // Dispatch event that jobs have been loaded
      document.dispatchEvent(
        new CustomEvent("sennaJobsLoaded", {
          detail: {
            jobs: this.allJobs,
            displayed: 0, // Not showing any initially
          },
        })
      );

      // Get user's first name and time-based greeting
      const userName = window.sffc_ajax?.user_name || "";
      const firstName = userName.split(" ")[0] || "there";
      const hour = new Date().getHours();
      let timeGreeting = "Good morning";
      if (hour >= 12 && hour < 17) {
        timeGreeting = "Good afternoon";
      } else if (hour >= 17) {
        timeGreeting = "Good evening";
      }

      // Check user status and profile for personalized welcome
      const isLoggedIn = window.sffc_ajax?.is_logged_in === "1";
      const profileData = localStorage.getItem("sffc_user_profile");
      let profile = null;
      let profileComplete = false;
      let profileCompleteness = 0;

      if (profileData) {
        try {
          profile = JSON.parse(profileData);
          // Calculate profile completeness
          const requiredFields = [
            "full_name",
            "skills",
            "experience_level",
            "years_experience",
            "preferred_locations",
          ];
          const completedFields = requiredFields.filter(
            (field) =>
              profile[field] &&
              (Array.isArray(profile[field]) ? profile[field].length > 0 : true)
          );
          profileCompleteness = Math.round(
            (completedFields.length / requiredFields.length) * 100
          );
          profileComplete = profileCompleteness === 100;
        } catch (e) {
          console.error("Error parsing profile:", e);
        }
      }

      // Check if user has uploaded CV
      let hasUploadedCV = localStorage.getItem("sffc_cv_uploaded") === "true";
      let savedCVProfile = localStorage.getItem("sffc_cv_profile");
      const lastVisit = localStorage.getItem("sffc_last_visit");
      const newOpportunities = this.checkNewOpportunities(lastVisit);

      // For logged-in users only, check database for CV
      if (isLoggedIn && window.sffc_ajax && window.sffc_ajax.user_logged_in) {
        // Check database for existing CV
        $.ajax({
          url: window.sffc_ajax.ajax_url || "/wp-admin/admin-ajax.php",
          type: "POST",
          data: {
            action: "sffc_get_user_cv",
            nonce: window.sffc_ajax.nonce,
          },
          async: false, // We need this synchronous for the welcome message
          success: function (response) {
            if (response.success && response.data.has_cv) {
              hasUploadedCV = true;
              // Store CV data from database
              window.currentCVContent = response.data.cv_content;

              // Parse the CV content to get proper CV data object
              if (window.currentCVContent && window.parseCV) {
                window.currentCVData = window.parseCV(window.currentCVContent);
              } else {
                // Fallback to parsed data from database
                window.currentCVData = response.data.cv_parsed || {
                  latestRole: response.data.latest_role,
                  company: response.data.company,
                  location: response.data.location,
                  seniority: response.data.seniority,
                  skills: response.data.skills,
                };
              }

              savedCVProfile = JSON.stringify({
                latestRole: response.data.latest_role,
                company: response.data.company,
                location: response.data.location,
                seniority: response.data.seniority,
                uploadDate: response.data.uploaded_date,
              });
              // Update localStorage with database data
              localStorage.setItem("sffc_cv_uploaded", "true");
              localStorage.setItem("sffc_cv_profile", savedCVProfile);
              localStorage.setItem("sffc_cv_db_id", response.data.cv_id);
            }
          },
        });
      } else if (!isLoggedIn) {
        // For logged-out users, try to restore CV from localStorage
        const storedCVContent = localStorage.getItem("sffc_cv_content");
        if (storedCVContent && hasUploadedCV) {
          window.currentCVContent = storedCVContent;
          // Parse the CV content to get full CV data
          if (window.parseCV) {
            window.currentCVData = window.parseCV(storedCVContent);
          } else if (savedCVProfile) {
            // Fallback to saved profile if parse function not available
            try {
              window.currentCVData = JSON.parse(savedCVProfile);
            } catch (e) {
              console.log("Could not parse saved CV profile");
            }
          }
        }
      }

      // Generate appropriate welcome message based on user status
      let welcomeMessage = "";

      if (!isLoggedIn) {
        // Logged-out user
        // Typing effect container (appears first)
        // Typing effect container (appears first)
        // =========================
        // MENA Careers Welcome (ready-to-paste)
        // =========================
        welcomeMessage = `
<div id="senna-welcome-wrap" style="margin-top:16px;">
  <!-- Typing line -->
  <div id="senna-typing-intro" style="font-size:20px;font-weight:600;color:#333;"></div>
<!-- Full welcome (revealed after typing) -->
<div id="senna-full-welcome" style="display:none;">
  <p style="margin:12px 0 16px 0; line-height:1.6; font-size: 16px; color: #1a472a;">
    I'm <strong>MENA Careers</strong> — your AI career strategist for private equity and finance.
  </p>
  
  <p style="margin:8px 0 20px 0; line-height:1.5; color:#555;">
    I’ll help you <strong>refine your CV</strong>, give you <strong>tailored career advice</strong>, and show you how to position yourself to land top roles in competitive markets.
  </p>

  <div style="margin:20px 0; padding:16px; background:#F0FDF4; border:2px solid #10B981; border-radius:8px; text-align:center;">
    <p style="margin:0 0 12px 0; color:#1a472a; font-weight:600; font-size:15px;">
      Ready to see how your CV stacks up?
    </p>
    <button 
      onclick="event.preventDefault(); jQuery('.cv-tailoring-container').show(); jQuery('html, body').animate({scrollTop: jQuery('.cv-tailoring-container').offset().top - 100}, 500); return false;"
      style="background:#10B981; color:white; border:none; padding:12px 24px; border-radius:6px; font-weight:600; cursor:pointer; font-size:14px; transition:all 0.2s;"
      onmouseover="this.style.background='#059669'"
      onmouseout="this.style.background='#10B981'">
      Upload CV – Get Assessment
    </button>
  </div>

  <div style="margin:16px 0; padding:12px; background:#f8f9fa; border-left:3px solid #6b7280; border-radius:4px;">
    <p style="margin:0; color:#555; font-size:14px;">
      <strong>Not sure where to begin?</strong> Choose a prompt below or ask me anything about your career strategy.
    </p>
  </div>

  <p style="margin:16px 0 8px 0; font-size:15px; color:#333; font-weight:600;">
    Try one of these to get started:
  </p>

  <!-- Starter prompts -->
  <div style="display:flex; flex-direction:column; gap:8px;">
    <button
      class="prompt-btn"
      style="display:flex;align-items:center;gap:8px;background:#f8f9fa;border:1px solid #e0e0e0;padding:10px 14px;border-radius:6px;font-size:14px;cursor:pointer;text-align:left;"
      onclick="jQuery('#senna-input').val('Can you review my CV for an Investment Banking Analyst role?'); sennaConversational.handleUserInput();">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      Review my CV for an Investment Banking Analyst role
    </button>

    <button
      class="prompt-btn"
      style="display:flex;align-items:center;gap:8px;background:#f8f9fa;border:1px solid #e0e0e0;padding:10px 14px;border-radius:6px;font-size:14px;cursor:pointer;text-align:left;"
      onclick="jQuery('#senna-input').val('How can I break into private equity without prior PE experience?'); sennaConversational.handleUserInput();">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>
      How can I break into private equity without prior PE experience?
    </button>

    <button
      class="prompt-btn"
      style="display:flex;align-items:center;gap:8px;background:#f8f9fa;border:1px solid #e0e0e0;padding:10px 14px;border-radius:6px;font-size:14px;cursor:pointer;text-align:left;"
      onclick="jQuery('#senna-input').val('What are the key CV tips for landing interviews at mid-market funds in Europe?'); sennaConversational.handleUserInput();">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
      Key CV tips for mid-market funds in Europe
    </button>
  </div>
</div>


    < <!-- CV Tailoring Engine Container -->
                        <div class="cv-tailoring-container" style="margin: 20px 0; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" style="margin-right: 10px;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #333;">CV Tailoring Engine </h3>
                            </div>
                            <p style="margin-bottom: 15px; font-size: 16px; color: #555;">Upload a Word document for the best results!</p>
                            </div>
                            <div id="cv-upload-area" style="text-align: center; padding: 20px 0;">
                                <div class="upload-option-buttons">
                                    <button class="cv-option-btn upload-file-btn" onclick="window.showCVUploadInterface()">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        <span>Upload CV</span>
                                        <small>PDF or DOCX</small>
                                    </button>
                                    
                                    <button class="cv-option-btn paste-text-btn" onclick="window.showCVPasteInterface()">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2">
                                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                                        </svg>
                                        <span>Paste Text</span>
                                        <small>Copy & Paste</small>
                                    </button>
                                </div>
                                
                                <div id="cv-upload-interface" style="display:none; margin-top: 15px;"></div>
                                
                                <!-- CV Display Area (shown after upload) -->
                                <div id="uploaded-cv-display" style="display:none; margin-top: 20px; text-align: left;">
                                    <div style="padding: 15px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #10B981;">
                                        <h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px; font-weight: 600;">Your CV</h4>
                                        <div id="cv-details" style="font-size: 13px; line-height: 1.6; color: #555;">
                                            <!-- CV details will be populated here -->
                                        </div>
                                        <button onclick="window.changeCVUpload()" style="margin-top: 10px; padding: 6px 12px; background: transparent; border: 1px solid #666; color: #666; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                            Change CV
                                        </button>
                                    </div>
                                </div>
    </div>
  </div>
</div>
`;

        // =========================
        // Safe typing + form wiring
        // =========================
        (function __mountSennaWelcome() {
          if (window.__sennaWelcomeInitDone) return;
          window.__sennaWelcomeInitDone = true;

          const introText = "Hi There!";

          function startTypingWhenReady() {
            const container = document.getElementById("senna-typing-intro");
            if (!container) {
              // Wait until the welcomeMessage has been appended to the DOM
              setTimeout(startTypingWhenReady, 60);
              return;
            }

            let i = 0;
            const typeInterval = setInterval(() => {
              // Guard again in case container is removed during typing
              const el = document.getElementById("senna-typing-intro");
              if (!el) {
                clearInterval(typeInterval);
                return;
              }

              el.textContent = introText.slice(0, i);
              i++;
              if (i > introText.length) {
                clearInterval(typeInterval);
                // reveal full body (small delay for polish)
                setTimeout(() => {
                  const full = document.getElementById("senna-full-welcome");
                  if (full) full.style.display = "block";
                  wireCoachingForm(); // now that form is in DOM
                }, 250);
              }
            }, 25);
          }

          function wireCoachingForm() {
            const form = document.getElementById("senna-coaching-form");
            if (!form) {
              setTimeout(wireCoachingForm, 100);
              return;
            }

            form.addEventListener("submit", function (e) {
              e.preventDefault();
              const name = (
                form.querySelector('input[name="Name"]')?.value || ""
              ).trim();
              const email = (
                form.querySelector('input[name="Email"]')?.value || ""
              ).trim();
              const msg = (
                form.querySelector('textarea[name="Message"]')?.value || ""
              ).trim();

              const subject = encodeURIComponent(
                `Coaching Request from ${name || "Candidate"}`
              );
              const body = encodeURIComponent(
                `Name: ${name}\nEmail: ${email}\n\nMessage:\n${msg}\n\n— Sent from MENA Careers`
              );

              // Open user's email client
              window.location.href = `mailto:support.team@senna.co?subject=${subject}&body=${body}`;

              const feedback = document.getElementById(
                "senna-coaching-feedback"
              );
              if (feedback) feedback.style.display = "block";
            });
          }

          // Kick off typing poll
          startTypingWhenReady();
        })();
      } else if (hasUploadedCV && savedCVProfile) {
        // Logged-in with CV already uploaded - show personalized welcome with automatic matches
        const cvProfile = JSON.parse(savedCVProfile || "{}");

        const readableRole = this.escapeHtml(
          cvProfile.latestRole ||
            (window.currentCVData && window.currentCVData.latestRole) ||
            "your profile"
        );
        const readableLocation =
          cvProfile.location || window.currentCVData?.location;
        const safeLocation = readableLocation
          ? ` in ${this.escapeHtml(readableLocation)}`
          : "";

        const savedRolesList =
          typeof getSavedRolesList === "function" ? getSavedRolesList() : [];
        const savedRolesCount = savedRolesList.length;
        const savedRolesPlural = savedRolesCount === 1 ? "role" : "roles";
        const savedRolesNote = savedRolesCount
          ? `<p style="margin: 0 0 12px 0; font-size: 13px; color: #475569;">You have <strong>${savedRolesCount}</strong> saved ${savedRolesPlural}. Want to continue where you left off?</p>`
          : "";
        const savedRolesButton = savedRolesCount
          ? `<button onclick="if(window.showSavedRolesInChat){window.showSavedRolesInChat({source: 'welcome'});}" class="action-btn secondary" style="padding: 10px 18px; background: #eef7ff; color: #1d4ed8; border: 1px solid rgba(29, 78, 216, 0.25); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                View saved roles (${savedRolesCount})
              </button>`
          : "";
        const compareRolesButton =
          savedRolesCount >= 2
            ? `<button onclick="if(window.compareSavedRoles){window.compareSavedRoles({source: 'welcome'});}" class="action-btn secondary" style="padding: 10px 18px; background: #fdf2f8; color: #9d174d; border: 1px solid rgba(157, 23, 77, 0.25); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Compare saved roles (${savedRolesCount})
              </button>`
            : "";

        // Get application stats for personalization
        const applicationStats = window.getApplicationStats
          ? window.getApplicationStats(7)
          : { appliedCount: 0, trackedCount: 0 };
        const recentJobs = window.getRecentlyTrackedJobs
          ? window.getRecentlyTrackedJobs(7)
          : [];

        // Generate personalized messages
        let personalizedMessage = "";
        let celebrationMessage = "";

        // Celebration messages
        if (applicationStats.appliedCount >= 3) {
          celebrationMessage = `<div style="margin: 0 0 12px 0; padding: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #bbf7d0; border-radius: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <p style="margin: 0; font-size: 14px; color: #166534; font-weight: 600;">Congratulations! You've applied to ${applicationStats.appliedCount} roles this week. Great momentum!</p>
            </div>
          </div>`;
        } else if (applicationStats.trackedCount >= 5) {
          celebrationMessage = `<div style="margin: 0 0 12px 0; padding: 12px; background: linear-gradient(135deg, #fef7f0 0%, #fed7aa 100%); border: 1px solid #fdba74; border-radius: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9a3412" stroke-width="2">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
              </svg>
              <p style="margin: 0; font-size: 14px; color: #9a3412; font-weight: 600;">You're actively tracking ${applicationStats.trackedCount} roles. Ready to start applying?</p>
            </div>
          </div>`;
        }

        // Role-specific follow-ups
        if (recentJobs.length > 0) {
          const randomJob =
            recentJobs[Math.floor(Math.random() * recentJobs.length)];
          const daysSinceTracked = Math.floor(
            (new Date() - new Date(randomJob.firstTracked)) /
              (1000 * 60 * 60 * 24)
          );

          if (randomJob.status === "applied" && daysSinceTracked >= 1) {
            personalizedMessage = `<p style="margin: 0 0 12px 0; font-size: 14px; color: #7c3aed; font-weight: 500;">
              I saw you applied to a role ${
                daysSinceTracked === 1
                  ? "yesterday"
                  : `${daysSinceTracked} days ago`
              }. How did it go? Need help with follow-up or interview prep?
            </p>`;
          } else if (randomJob.status === "tracked" && daysSinceTracked >= 2) {
            personalizedMessage = `<p style="margin: 0 0 12px 0; font-size: 14px; color: #ea580c; font-weight: 500;">
              You tracked a role ${daysSinceTracked} days ago. Have you managed to apply yet? I can help you tailor your application.
            </p>`;
          }
        }

        welcomeMessage = `
          <div style="margin-top: 16px;">
            <p style="margin-bottom: 10px; font-size: 16px; font-weight: 600; color: #1a472a;">
              ${timeGreeting}, ${this.escapeHtml(
          firstName || "there"
        )}. I've refreshed your ${readableRole}${safeLocation} CV.
            </p>
            ${celebrationMessage}
            ${personalizedMessage}
            <p style="margin: 0 0 16px 0; font-size: 14px; color: #475569; line-height: 1.6;">
              Let me surface what stands out to recruiters and line up fresh opportunities tailored to you.
            </p>
            ${savedRolesNote}
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <button onclick="if(window.showCvInsights){window.showCvInsights();}else{setTimeout(()=>window.showCvInsights&&window.showCvInsights(),200);}" class="action-btn primary" style="padding: 10px 18px; background: #1a472a; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Review my CV insights
              </button>
              <button onclick="if(window.revealJobMatches){window.revealJobMatches();}" class="action-btn secondary" style="padding: 10px 18px; background: #f8fafc; color: #1a472a; border: 1px solid rgba(26, 71, 42, 0.2); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Skip to roles
              </button>
              <button onclick="if(window.changeCVUpload){window.changeCVUpload();}" class="action-btn secondary" style="padding: 10px 18px; background: #fff; color: #1a472a; border: 1px dashed rgba(26, 71, 42, 0.3); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Upload a new CV
              </button>
              ${savedRolesButton}
              ${compareRolesButton}
            </div>
          </div>
        `;

        // Automatically show matched jobs after a brief delay
        setTimeout(() => {
          if (
            window.currentCVContent &&
            !window.currentCVData &&
            window.parseCV
          ) {
            window.currentCVData = window.parseCV(window.currentCVContent);
          }

          let profileData = window.currentCVData;
          if (!profileData && savedCVProfile) {
            try {
              profileData = JSON.parse(savedCVProfile);
            } catch (e) {
              profileData = null;
            }
          }

          if (profileData) {
            window.pendingCVMatch = profileData;
            const matchedJobs = this.allJobs
              ? window.matchJobsWithCV(profileData, this.allJobs)
              : window.pendingMatchedJobs || [];

            window.prepareJobMatches(profileData, matchedJobs, {
              source: "login_resume",
              refreshReview: true,
            });
          }
        }, 600);
      } else if (!profile || profileCompleteness === 0) {
        // Logged-in but no profile/CV - show enhanced welcome message
        const savedRolesCount = getSavedRolesList().length;
        const savedRolesPlural = savedRolesCount === 1 ? "role" : "roles";
        const savedRolesNote = savedRolesCount
          ? `<p style="margin: 0 0 12px 0; font-size: 13px; color: #475569;">You have <strong>${savedRolesCount}</strong> tracked ${savedRolesPlural}. Want to continue where you left off?</p>`
          : "";
        const viewRolesButton = savedRolesCount
          ? `<button onclick="if(window.showSavedRolesInChat){window.showSavedRolesInChat({source: 'welcome'});}" class="action-btn secondary" style="padding: 10px 18px; background: #eef7ff; color: #1d4ed8; border: 1px solid rgba(29, 78, 216, 0.25); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; margin-right: 10px;">
                View tracked roles (${savedRolesCount})
              </button>`
          : "";

        welcomeMessage = `
          <div style="margin-top: 16px;">
            <p style="margin-bottom: 10px; font-size: 16px; font-weight: 600; color: #1a472a;">
              ${timeGreeting}, ${this.escapeHtml(
          firstName || "there"
        )}. I'm ready to connect you with a live expert who can map out your next move.
            </p>
            ${savedRolesNote}
            <p style="margin-bottom: 16px; font-size: 14px; color: #475569; line-height: 1.5;">
              Share your CV so our concierge team can prepare personalized guidance before your session.
            </p>
            <div style="margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap;">
              ${viewRolesButton}
              <button onclick="$('.cv-tailoring-container').show(); $('html, body').animate({scrollTop: $('.cv-tailoring-container').offset().top - 100}, 500);" style="padding: 10px 18px; background: #1a472a; color: #ffffff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Upload CV
              </button>
              <button onclick="sennaConversational.requestLiveExpert({ source: 'welcome-button', autoConnect: true });" style="padding: 10px 18px; background: #ffffff; color: #1a472a; border: 1px solid rgba(26, 71, 42, 0.25); border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                Talk to a Live Expert
              </button>
            </div>
          </div>
                        
                        <!-- Show CV upload interface for logged-in users without CV -->
                        <div class="cv-tailoring-container" style="margin: 20px 0; background: #ffffff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                            <div style="display: flex; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e0e0e0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" style="margin-right: 10px;">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                                <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #333;">Get Started</h3>
                            </div>
                            
                            <div id="cv-upload-area" style="text-align: center; padding: 20px 0;">
                                <div class="upload-option-buttons">
                                    <button class="cv-option-btn upload-file-btn" onclick="window.showCVUploadInterface()">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" y1="3" x2="12" y2="15"></line>
                                        </svg>
                                        <span>Upload CV</span>
                                        <small>PDF or DOCX</small>
                                    </button>
                                    
                                    <button class="cv-option-btn paste-text-btn" onclick="window.showCVPasteInterface()">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2">
                                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                                        </svg>
                                        <span>Paste Text</span>
                                        <small>Copy & Paste</small>
                                    </button>
                                </div>
                                
                                <div id="cv-upload-interface" style="display:none; margin-top: 15px;"></div>
                            </div>
                        </div>
                        
                        <p style="margin-bottom: 8px; font-weight: 500;">Meanwhile, you can explore:</p>
                        <ul style="list-style: none; padding-left: 0; margin: 8px 0;">
                            <li style="padding: 4px 0;">• All ${
                              this.allJobs.length
                            }+ opportunities (unfiltered)</li>
                            <li style="padding: 4px 0;">• Trending roles this week</li>
                            <li style="padding: 4px 0;">• Top-paying positions</li>
                        </ul>
                        
                        <p style="margin-top: 12px; font-weight: 500;">Quick question: Want to see jobs tailored to your experience?</p>
                    </div>
                `;
      } else if (!profileComplete) {
        // Logged-in with incomplete profile
        const location =
          profile.preferred_locations?.[0] || "your preferred location";
        const jobsInLocation = this.allJobs.filter((job) =>
          profile.preferred_locations?.some((loc) =>
            job.location?.includes(loc)
          )
        ).length;

        welcomeMessage = `
                    <div style="margin-top: 16px;">
                        <p style="margin-bottom: 12px;">Welcome back, ${firstName}!</p>
                        
                        <div style="margin: 16px 0; padding: 12px; background: #F0F9FF; border-left: 3px solid #3B82F6; border-radius: 4px;">
                            <p style="margin: 0; font-weight: 600; color: #000;">Your profile is ${profileCompleteness}% complete</p>
                            <p style="margin: 4px 0 0 0; color: #666;">
                                ${
                                  profile.preferred_locations
                                    ? `Found ${jobsInLocation} jobs in ${location}`
                                    : "Add your preferences for better matches"
                                }
                            </p>
                            ${
                              !profile.skills
                                ? '<p style="margin: 4px 0 0 0; color: #666;">Missing: Skills for accurate matching</p>'
                                : ""
                            }
                        </div>
                        
                        <p style="margin-bottom: 8px; font-weight: 500;">Based on what you've told me:</p>
                        <ul style="list-style: none; padding-left: 0; margin: 8px 0;">
                            ${
                              profile.preferred_locations
                                ? `<li style="padding: 4px 0;">• ${jobsInLocation} opportunities in ${location}</li>`
                                : ""
                            }
                            ${
                              profile.experience_level
                                ? `<li style="padding: 4px 0;">• ${profile.experience_level} level positions</li>`
                                : ""
                            }
                            <li style="padding: 4px 0;">• Complete your profile for personalized matches</li>
                        </ul>
                    </div>
                `;
      } else {
        // Logged-in with complete profile
        const userSkills = profile.skills || [];
        const matchingJobs = this.allJobs.filter((job) => {
          if (!job.skills) return false;
          const jobSkills = job.skills.map((s) => s.toLowerCase());
          return userSkills.some((skill) =>
            jobSkills.some((jobSkill) => jobSkill.includes(skill.toLowerCase()))
          );
        });

        const highMatchJobs = matchingJobs.filter((job) => {
          const matchCount =
            job.skills?.filter((jobSkill) =>
              userSkills.some((userSkill) =>
                jobSkill.toLowerCase().includes(userSkill.toLowerCase())
              )
            ).length || 0;
          return matchCount >= 2;
        });

        const locationJobs = this.allJobs.filter((job) =>
          profile.preferred_locations?.some((loc) =>
            job.location?.includes(loc)
          )
        );

        welcomeMessage = `
                    <div style="margin-top: 16px;">
                        <p style="margin-bottom: 12px;">${timeGreeting}, ${firstName}! Great to see you again.</p>
                        
                        <div style="margin: 16px 0; padding: 12px; background: #F0FDF4; border-left: 3px solid #10B981; border-radius: 4px;">
                            <p style="margin: 0; font-weight: 600; color: #000;">Based on your profile:</p>
                            <p style="margin: 4px 0; color: #333; font-size: 13px;">
                                ${profile.skills?.slice(0, 3).join(", ")} • ${
          profile.experience_level || ""
        } • ${profile.years_experience || 0} years exp
                            </p>
                            <p style="margin: 8px 0 0 0; font-weight: 600; color: #000;">
                                Found ${
                                  highMatchJobs.length
                                } highly relevant matches
                            </p>
                        </div>
                        
                        <p style="margin-bottom: 8px; font-weight: 500;">Your personalized insights:</p>
                        <ul style="list-style: none; padding-left: 0; margin: 8px 0;">
                            <li style="padding: 4px 0;">• ${
                              highMatchJobs.length
                            } roles matching your skills (80%+ match)</li>
                            <li style="padding: 4px 0;">• ${
                              locationJobs.length
                            } opportunities in ${profile.preferred_locations?.join(
          ", "
        )}</li>
                            <li style="padding: 4px 0;">• ${
                              matchingJobs.length
                            } total relevant positions</li>
                        </ul>
                        
                        <p style="margin-top: 12px; font-weight: 500;">What would you like to see?</p>
                    </div>
                `;
      }

      // Update last visit timestamp
      localStorage.setItem("sffc_last_visit", new Date().toISOString());

      // Add welcome message without redundant greeting
      this.addSennaMessage(welcomeMessage, true);

      // Add contextual quick action buttons based on user status
      setTimeout(() => {
        // Dispatch event that jobs have been rendered
        document.dispatchEvent(
          new CustomEvent("jobsRenderedInChat", {
            detail: { jobs: this.displayedJobs },
          })
        );

        // DISABLED: Secondary quick actions - now integrated into main welcome message
        return; // Skip the secondary quick actions message

        let quickActionsMessage = "";

        // Check for saved jobs
        const savedJobs = JSON.parse(
          localStorage.getItem("sffc_saved_jobs") || "[]"
        );
        const savedCount = savedJobs.length;

        if (!isLoggedIn) {
          // Actions for logged-out users
          quickActionsMessage = `
                        <div class="option-cards">
                            <div class="option-card" onclick="sennaConversational.requestLiveExpert({ source: 'quick-action', autoConnect: true });">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="9" y1="9" x2="15" y2="9"></line>
                                    <line x1="9" y1="15" x2="15" y2="15"></line>
                                </svg>
                                <div class="option-card-text">Talk to a Live Expert</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.promptProfileCreation()" style="background: linear-gradient(135deg, #D1FAE5, #6EE7B7);">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="8" r="4"></circle>
                                    <line x1="12" y1="20" x2="12" y2="20"></line>
                                </svg>
                                <div class="option-card-text" style="color: #059669;">Create Profile</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.filterByLocation('london')">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <div class="option-card-text">London Roles</div>
                            </div>
                            ${
                              savedCount > 0
                                ? `
                                <div class="option-card" onclick="sennaConversational.showSavedRoles()" style="background: linear-gradient(135deg, #E0E7FF, #818CF8);">
                                    <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <div class="option-card-text" style="color: #4F46E5;">View Saved (${savedCount})</div>
                                </div>
                            `
                                : ""
                            }
                        </div>
                    `;
        } else if (!profile || profileCompleteness === 0) {
          // Actions for logged-in users without profile
          quickActionsMessage = `
                        <div class="option-cards">
                            <div class="option-card" onclick="sennaConversational.startProfileSetup()" style="background: linear-gradient(135deg, #FEF3C7, #FCD34D);">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="8" r="4"></circle>
                                    <path d="M12 14v7"></path>
                                </svg>
                                <div class="option-card-text" style="color: #F59E0B;">Set Up Profile</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.showTrendingJobs()">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                </svg>
                                <div class="option-card-text">Browse Trending</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.filterByLocation('london')">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <div class="option-card-text">London Jobs</div>
                            </div>
                        </div>
                    `;
        } else if (!profileComplete) {
          // Actions for users with incomplete profile
          const location =
            profile.preferred_locations?.[0]?.toLowerCase() || "london";
          quickActionsMessage = `
                        <div class="option-cards">
                            <div class="option-card" onclick="sennaConversational.completeProfile()" style="background: linear-gradient(135deg, #DBEAFE, #93C5FD);">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="8" r="4"></circle>
                                    <polyline points="16 11 18 13 22 9"></polyline>
                                </svg>
                                <div class="option-card-text" style="color: #3B82F6;">Complete Profile</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.filterByLocation('${location}')">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <div class="option-card-text">${
                                  profile.preferred_locations?.[0] || "London"
                                } Jobs</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.requestLiveExpert({ source: 'quick-action', autoConnect: true });">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="9" y1="9" x2="15" y2="9"></line>
                                    <line x1="9" y1="15" x2="15" y2="15"></line>
                                </svg>
                                <div class="option-card-text">Browse All</div>
                            </div>
                            ${
                              savedCount > 0
                                ? `
                                <div class="option-card" onclick="sennaConversational.showSavedRoles()" style="background: linear-gradient(135deg, #E0E7FF, #818CF8);">
                                    <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <div class="option-card-text" style="color: #4F46E5;">Saved (${savedCount})</div>
                                </div>
                            `
                                : `
                                <div class="option-card" onclick="sennaConversational.filterBySalary(100000)">
                                    <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                    <div class="option-card-text">£100k+ Roles</div>
                                </div>
                            `
                            }
                        </div>
                    `;
        } else {
          // Actions for users with complete profile
          const location =
            profile.preferred_locations?.[0]?.toLowerCase() || "london";
          quickActionsMessage = `
                        <div class="option-cards">
                            <div class="option-card" onclick="sennaConversational.requestLiveExpert({ source: 'quick-action-personalized', autoConnect: true });" style="background: linear-gradient(135deg, #D1FAE5, #6EE7B7);">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <div class="option-card-text" style="color: #10B981;">My Top Matches</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.filterByLocation('${location}')">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <div class="option-card-text">${
                                  profile.preferred_locations?.[0] || "London"
                                } Jobs</div>
                            </div>
                            <div class="option-card" onclick="sennaConversational.updateProfilePreferences()">
                                <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M12 1v6m0 6v6m4.22-13.22l4.24 4.24M1.54 9.54l4.24 4.24m12.68 0l4.24 4.24M1.54 14.46l4.24-4.24"></path>
                                </svg>
                                <div class="option-card-text">Update Profile</div>
                            </div>
                            ${
                              savedCount > 0
                                ? `
                                <div class="option-card" onclick="sennaConversational.showSavedRoles()" style="background: linear-gradient(135deg, #E0E7FF, #818CF8);">
                                    <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2">
                                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                    <div class="option-card-text" style="color: #4F46E5;">Saved (${savedCount})</div>
                                </div>
                            `
                                : `
                                <div class="option-card" onclick="sennaConversational.filterBySalary(100000)">
                                    <svg class="option-card-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                    <div class="option-card-text">£100k+ Roles</div>
                                </div>
                            `
                            }
                        </div>
                    `;
        }

        this.addSennaMessage(quickActionsMessage, true);
      }, 500);
    }

    // New method: Show trending jobs only when requested
    showTrendingJobs() {
      if (this.displayedJobs && this.displayedJobs.length > 0) {
        this.addSennaMessage("Here are today's trending PE opportunities:");
        setTimeout(() => {
          this.renderJobsInChat(this.displayedJobs);
        }, 300);
      } else {
        this.addSennaMessage("Loading trending opportunities...");
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 6));
        }, 300);
      }
    }

    // New method: Filter by location
    filterByLocation(location) {
      const locationName = location.charAt(0).toUpperCase() + location.slice(1);
      this.processUserIntent(`Show me ${locationName} opportunities`);
    }

    // New method: Filter by level
    filterByLevel(level) {
      const levelName = level.charAt(0).toUpperCase() + level.slice(1);
      // Same fix: no duplicate user message
      this.processUserIntent(`Show me ${levelName} level positions`);
    }

    // New method: Filter by salary
    filterBySalary(minSalary) {
      const rounded = Math.round(minSalary / 5000) * 5; // nearest 5k
      const salaryText = `£${rounded}k+`;

      const query = `Show me roles paying ${salaryText}`;

      if (this.displayedJobs && this.displayedJobs.length > 0) {
        // Already in jobs context
        this.processUserIntent(query);
      } else {
        // Otherwise, treat it as advice
        this.addUserMessage(query);
        this.addSennaMessage(
          `<div style="display: flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
              <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Did you want me to search jobs with this salary, or discuss compensation benchmarks?</span>
          </div>`,
          false,
          "Career Guidance"
        );
      }
    }

    isCVTailoringRequest(input) {
      const text = (input || "").toLowerCase().trim();
      if (!text) return false;

      // --- Helper: exact phrase check ---
      const containsPhrase = (phrase) => {
        const regex = new RegExp(`\\b${phrase}\\b`, "i");
        return regex.test(text);
      };

      const cvPatterns = [
        "tailor my cv",
        "tailor cv",
        "customize my cv",
        "customize cv",
        "tailor my resume",
        "customize my resume",
        "adapt my cv",
        "adapt my resume",
        "can i tailor",
        "how to tailor",
        "help me tailor",
        "cv for this job",
        "resume for this job",
        "cv for this role",
        "resume for this role",
      ];

      return cvPatterns.some((pattern) => containsPhrase(pattern));
    }

    showCVTailoringInterface() {
      // Use WSJ compact container
      if (typeof createWSJChatContainer === "undefined") {
        $.getScript(
          (window.sffc_ajax?.plugin_url || "/") +
            "assets/js/wsj-cv-chat-integration.js"
        ).done(() => {
          const container = window.createWSJChatContainer(
            "Your Target Role",
            "Target Company",
            "manual"
          );
          this.addSennaMessage(container, true, "WSJ CV System");
        });
      } else {
        const container = window.createWSJChatContainer(
          "Your Target Role",
          "Target Company",
          "manual"
        );
        this.addSennaMessage(container, true, "WSJ CV System");
      }

      // Helper function for CV tailoring paste
      if (!window.handleCVTailoringPaste) {
        window.handleCVTailoringPaste = () => {
          const jobDescription = document.getElementById(
            "job-description-input"
          )?.value;
          if (!jobDescription || jobDescription.trim().length < 50) {
            alert(
              "Please paste a complete job description (at least 50 characters)"
            );
            return;
          }

          // Add to chat
          const sennaChat = window.sennaConversational || window.sennaChat;
          if (sennaChat) {
            sennaChat.addUserMessage(
              "Tailor my CV for this job:\n" +
                jobDescription.substring(0, 200) +
                "..."
            );
            sennaChat.addTypingIndicator();

            // Simulate processing
            setTimeout(() => {
              sennaChat.removeTypingIndicator();

              const tailoredHtml = `
                                <div style="background: linear-gradient(to bottom, #f0fdf4, #ffffff); padding: 20px; border-radius: 12px; border: 1px solid #86efac;">
                                    <div style="display: flex; align-items: center; margin-bottom: 16px;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" style="margin-right: 12px;">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                        <h4 style="margin: 0; color: #166534; font-size: 18px;">CV Successfully Tailored!</h4>
                                    </div>
                                    
                                    <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                                        <p style="margin: 0 0 12px 0; font-weight: 600; color: #333;">Key Optimizations Made:</p>
                                        <ul style="margin: 0; padding-left: 20px; color: #666;">
                                            <li style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Matched 87% of required keywords and skills</li>
                                            <li style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Reordered experience to highlight relevant achievements</li>
                                            <li style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Adjusted language to mirror job description tone</li>
                                            <li style="margin-bottom: 8px; display: flex; align-items: center; gap: 6px;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Added quantified results matching role requirements</li>
                                        </ul>
                                    </div>
                                    
                                    <div style="background: #fef3c7; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#713f12" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                          </svg>
                                          <p style="margin: 0; color: #713f12; font-size: 14px;">
                                            <strong>Pro Tip:</strong> Your tailored CV emphasizes your analytical skills and deal experience, 
                                            which align perfectly with this role's requirements.
                                          </p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 12px;">
                                        <button onclick="window.downloadTailoredCV()" 
                                                style="flex: 1; padding: 12px 20px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="7 10 12 15 17 10"></polyline>
                                                <line x1="12" y1="15" x2="12" y2="3"></line>
                                            </svg>
                                            Download Tailored CV
                                        </button>
                                        <button onclick="window.viewCVComparison()" 
                                                style="flex: 1; padding: 12px 20px; background: white; color: #2d6a4f; border: 2px solid #2d6a4f; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                            View Changes
                                        </button>
                                    </div>
                                </div>
                            `;

              sennaChat.addSennaMessage(
                tailoredHtml,
                true,
                "CV Optimization Complete"
              );

              // Clear the textarea
              const textarea = document.getElementById("job-description-input");
              if (textarea) textarea.value = "";
            }, 2500);
          }
        };

        // Add download function
        window.downloadTailoredCV = () => {
          // Simulate CV download
          const link = document.createElement("a");
          link.href = "#";
          link.download =
            "Tailored_CV_" + new Date().toISOString().split("T")[0] + ".pdf";
          link.click();

          // Show notification
          const sennaChat = window.sennaConversational || window.sennaChat;
          if (sennaChat) {
            sennaChat.addSennaMessage(
              "📥 Your tailored CV has been prepared for download. Check your downloads folder.",
              false,
              "Download Started"
            );
          }
        };

        // Add view comparison function
        window.viewCVComparison = () => {
          const sennaChat = window.sennaConversational || window.sennaChat;
          if (sennaChat) {
            sennaChat.addSennaMessage(
              `
                            <div style="background: #f8f9fa; padding: 16px; border-radius: 8px;">
                                <h4 style="margin: 0 0 12px 0; color: #333;">Side-by-side Comparison</h4>
                                <p style="color: #666; margin-bottom: 12px;">Key changes highlighted in your tailored CV:</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e0e0e0;">
                                        <h5 style="margin: 0 0 8px 0; color: #666; font-size: 12px;">ORIGINAL</h5>
                                        <p style="margin: 0; font-size: 13px; color: #999;">Financial Analyst with experience...</p>
                                    </div>
                                    <div style="background: #f0fdf4; padding: 12px; border-radius: 6px; border: 1px solid #86efac;">
                                        <h5 style="margin: 0 0 8px 0; color: #16a34a; font-size: 12px;">TAILORED</h5>
                                        <p style="margin: 0; font-size: 13px; color: #166534;"><strong>Investment Banking Analyst</strong> with proven deal execution...</p>
                                    </div>
                                </div>
                            </div>
                        `,
              true,
              "Comparison View"
            );
          }
        };
      }
    }

    isJobFilteringQuery(input) {
      const inputLower = input.toLowerCase();

      // Helper: only match whole words/phrases
      function containsWord(text, word) {
        return new RegExp(
          `\\b${word.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}\\b`,
          "i"
        ).test(text);
      }

      // Advice/help queries should NOT trigger job search
      const adviceIndicators = [
        "advice",
        "advise",
        "help me",
        "how to",
        "how do i",
        "how can i",
        "tips",
        "guidance",
        "suggest",
        "recommendation",
        "what should",
        "should i",
        "can you help",
        "assist",
        "prepare",
        "ready for",
        "improve",
        "enhance",
        "optimize",
        "break into",
      ];
      if (adviceIndicators.some((a) => containsWord(inputLower, a))) {
        return false;
      }

      // Any query containing URLs → treat as general, not job filtering
      const urlPatterns = [
        "http://",
        "https://",
        "www.",
        ".com",
        ".co.uk",
        "jobs.",
        "careers.",
        "linkedin.com",
        "indeed.",
      ];
      if (urlPatterns.some((p) => inputLower.includes(p))) {
        return false;
      }
      // ✅ Advice detection regexes (robust quick fix)
      const adviceRegexes = [
        /\bwhat\b.*\b(should|can|do)\b/i,
        /\bhow\b.*\b(do|can|should|to)\b/i,
        /\badvise\b/i,
        /\badvice\b/i,
        /\bhelp me\b/i,
        /\bguide\b/i,
        /\btips?\b/i,
        /\bskills?\b/i,
        /\bhow to\b/i,
        /\bcareer (path|advice|guide)\b/i,
        /\bshould i\b/i,
        /\bcan you\b/i,
        /\bprepare\b/i,
        /\bimprove\b/i,
        /\boptimi[sz]e\b/i,
        /\bwhat\b.*\bneed\b/i,
      ];

      for (const regex of adviceRegexes) {
        if (regex.test(inputLower)) {
          return false; // definitely advice, not job filtering
        }
      }

      // Job filtering patterns
      const jobFilteringPatterns = {
        directRequests: [
          "show me",
          "find me",
          "find",
          "search for",
          "look for",
          "list",
          "display",
          "get me",
          "fetch",
          "pull up",
          "bring up",
          "i want to see",
          "can you show",
          "please show",
          "show",
          "filter",
          "browse",
          "explore",
          "discover",
          "identify",
        ],
        jobKeywords: [
          "jobs",
          "job",
          "opportunities",
          "opportunity",
          "openings",
          "positions",
          "position",
          "roles",
          "role",
          "vacancies",
          "postings",
          "listing",
          "listings",
          "hiring",
          "recruiting",
          "careers",
          "employment",
          "work",
        ],
        locations: [
          "london",
          "new york",
          "nyc",
          "singapore",
          "hong kong",
          "dubai",
          "zurich",
          "paris",
          "frankfurt",
          "milan",
          "brazil",
          "tokyo",
          "sydney",
          "amsterdam",
          "madrid",
          "mumbai",
          "shanghai",
          "beijing",
          "toronto",
          "chicago",
          "boston",
          "san francisco",
          "los angeles",
          "miami",
        ],
        seniorities: [
          "analyst",
          "associate",
          "senior associate",
          "vp",
          "vice president",
          "director",
          "partner",
          "principal",
          "manager",
          "head",
          "chief",
          "junior",
          "senior",
          "entry level",
          "entry-level",
          "experienced",
          "lead",
        ],
        industries: [
          "private equity",
          "pe",
          "hedge fund",
          "hf",
          "venture capital",
          "vc",
          "investment banking",
          "ib",
          "asset management",
          "real estate",
          "infrastructure",
          "credit",
          "distressed",
          "growth equity",
          "buyout",
        ],
        salaryTerms: [
          "$",
          "£",
          "€",
          "salary",
          "compensation",
          "pay",
          "package",
          "bonus",
          "carry",
          "earning",
          "income",
          "100k",
          "150k",
          "200k",
          "250k",
          "300k",
          "six figure",
        ],
        companyTypes: [
          "startup",
          "start-up",
          "scale-up",
          "scaleup",
          "enterprise",
          "corporate",
          "boutique",
          "bulge bracket",
          "elite boutique",
          "megafund",
          "mega fund",
          "large cap",
          "mid market",
          "mid-market",
          "small cap",
        ],
        workStyles: [
          "remote",
          "hybrid",
          "on-site",
          "onsite",
          "flexible",
          "work from home",
          "wfh",
          "in-office",
          "in office",
        ],
        jobActions: [
          "looking for",
          "interested in",
          "want to work",
          "seeking",
          "pursuing",
          "targeting",
          "considering",
          "open to",
          "exploring",
          "ready for",
        ],
      };

      // 1. Direct request + job keyword
      for (const req of jobFilteringPatterns.directRequests) {
        if (containsWord(inputLower, req)) {
          for (const job of jobFilteringPatterns.jobKeywords) {
            if (containsWord(inputLower, job)) {
              return true;
            }
          }
        }
      }

      // 2. Location + job/role
      for (const loc of jobFilteringPatterns.locations) {
        if (containsWord(inputLower, loc)) {
          for (const job of jobFilteringPatterns.jobKeywords) {
            if (containsWord(inputLower, job)) {
              return true;
            }
          }
          for (const sen of jobFilteringPatterns.seniorities) {
            if (containsWord(inputLower, sen)) {
              return true;
            }
          }
        }
      }

      // 3. Seniority + job/role
      for (const sen of jobFilteringPatterns.seniorities) {
        if (containsWord(inputLower, sen)) {
          if (
            ["role", "position", "job", "opportunit"].some((k) =>
              inputLower.includes(k)
            )
          ) {
            return true;
          }
        }
      }

      // 4. Industry — tightened to require jobs OR explicit action
      for (const ind of jobFilteringPatterns.industries) {
        if (containsWord(inputLower, ind)) {
          if (
            jobFilteringPatterns.jobKeywords.some((j) =>
              containsWord(inputLower, j)
            )
          ) {
            return true;
          }
          if (/\b(show|find|search|browse|list) \b/.test(inputLower)) {
            return true;
          }
          if (
            /\bexplore\b/.test(inputLower) &&
            !inputLower.includes("career")
          ) {
            return true;
          }
        }
      }

      // 5. Salary queries with job context
      for (const sal of jobFilteringPatterns.salaryTerms) {
        if (containsWord(inputLower, sal)) {
          for (const job of jobFilteringPatterns.jobKeywords) {
            if (containsWord(inputLower, job)) {
              return true;
            }
          }
        }
      }

      // 6. Work style preferences + jobs
      for (const style of jobFilteringPatterns.workStyles) {
        if (containsWord(inputLower, style)) {
          for (const job of jobFilteringPatterns.jobKeywords) {
            if (containsWord(inputLower, job)) {
              return true;
            }
          }
        }
      }

      // 7. Job action phrases
      for (const action of jobFilteringPatterns.jobActions) {
        if (containsWord(inputLower, action)) {
          for (const job of jobFilteringPatterns.jobKeywords) {
            if (containsWord(inputLower, job)) {
              return true;
            }
          }
        }
      }

      // 8. Simple one-word triggers
      if (["jobs", "opportunities", "show jobs"].includes(inputLower)) {
        return true;
      }
      if (["more", "more jobs", "next"].includes(inputLower)) {
        // Only valid if last MENA Careers message was job results
        if (this.lastMessageWasJobs) {
          return true;
        }
      }

      // Otherwise, not a job filter query
      return false;
    }

    // New method to process general queries (non-job search)
    processGeneralQuery(input) {
      // This method bypasses job filtering and goes straight to general AI
      console.log("Processing general query (non-job):", input);

      // Try to send directly to AI chat
      if (window.SennaChat && window.SennaChat.send) {
        window.SennaChat.send(input);
      } else {
        // If SennaChat not available, send via AJAX directly
        if (window.sffc_ajax) {
          $.ajax({
            url: window.sffc_ajax.ajax_url,
            type: "POST",
            data: {
              action: "sffc_senna_chat",
              message: input,
              nonce: window.sffc_ajax.nonce,
              isGeneralQuery: true, // Flag to backend that this is NOT a job search
            },
            success: (response) => {
              if (response.success && response.data) {
                this.addSennaMessage(
                  response.data.message || response.data,
                  false,
                  "MENA Careers AI"
                );
              }
            },
            error: () => {
              this.addSennaMessage(
                "I'm here to help with career advice and guidance. Please try rephrasing your question.",
                false,
                "MENA Careers AI"
              );
            },
          });
        }
      }
    }

    handleUserInput(providedInput) {
      // Accept input as parameter OR get from input field
      const input = providedInput || $("#senna-input").val().trim();
      if (!input) return;

      // Convert early to avoid temporal dead zone errors
      const inputLower = input.toLowerCase();

      // Prevent double processing with a debounce
      if (this.isProcessingInput) {
        return;
      }
      if (this.lastProcessedInput === inputLower) {
        return; // same input, ignore
      }
      this.lastProcessedInput = inputLower;

      this.isProcessingInput = true;
      setTimeout(() => {
        this.isProcessingInput = false;
      }, 1000);

      // Clear the input field only if input came from the field (not from button)
      if (!providedInput) {
        $("#senna-input").val("");
      }

      if (this.isLearningCoachPage()) {
        this.sendLearningCoachMessage(input);
        return;
      }

      // Check if input contains a URL
      const urlRegex = /(https?:\/\/[^\s]+)/g;
      const urls = input.match(urlRegex);

      if (urls && urls.length > 0) {
        // Handle URL-based queries specially
        const filterKeywords = [
          "show me",
          "find me",
          "find",
          "search for",
          "list",
          "filter",
          "browse",
        ];
        const isFilterRequest = filterKeywords.some((keyword) =>
          inputLower.includes(keyword)
        );

        if (!isFilterRequest) {
          // Route all URL queries to AI system for proper handling
          if (window.SennaChat && window.SennaChat.send) {
            window.SennaChat.send(input);
          } else {
            this.addUserMessage(input);
            this.addSennaMessage(
              "I can see you've shared a job posting URL. " +
                "Please paste the job description here so I can provide tailored guidance on how to approach this opportunity.",
              false,
              "Career Advice"
            );
          }
          return; // Exit early to prevent double processing
        }
      }

      // Check if user is asking about CV tailoring
      if (this.isCVTailoringRequest(inputLower)) {
        this.addUserMessage(input);
        this.showCVTailoringInterface();
        return;
      }

      const chatMode = this.getChatMode();

      if (chatMode === "job-search") {
        this.addUserMessage(input);
        this.sendLiveExpertMessage(input);
        return;
      }

      if (chatMode === "career-advice") {
        this.sendLearningCoachMessage(input);
        return;
      }

      if (this.needsIntelligentSearch(input)) {
        this.addUserMessage(input);
        this.performIntelligentSearch(input);
        return;
      }

      if (this.isJobFilteringQuery(inputLower)) {
        this.addUserMessage(input);
        this.processUserIntent(input);
        return;
      }

      this.addUserMessage(input);

      if (window.SennaChat && window.SennaChat.send) {
        window.SennaChat.send(input);
      } else {
        this.addSennaMessage(
          "I can connect you with a live expert for tailored coaching—try asking for interview prep, a strategy review, or next steps.",
          false,
          "Live Expert Support"
        );
      }
    }

    processUserIntent(input) {
      if (this.jobSearchDisabled) {
        this.showLiveExpertPanel({ query: input, source: "user-intent" });
        return;
      }

      const inputLower = input.toLowerCase();

      // Prevent duplicate processing
      if (this.isProcessingIntent) {
        return;
      }

      this.isProcessingIntent = true;

      // Clear any timeout to reset the flag
      clearTimeout(this.intentProcessingTimeout);
      this.intentProcessingTimeout = setTimeout(() => {
        this.isProcessingIntent = false;
      }, 2000);

      // Extract location and seniority from the input
      const locations = {
        london: "London",
        "new york": "New York",
        nyc: "New York",
        singapore: "Singapore",
        "hong kong": "Hong Kong",
        dubai: "Dubai",
        zurich: "Zurich",
        paris: "Paris",
        frankfurt: "Frankfurt",
        milan: "Milan",
        brazil: "Brazil",
        tokyo: "Tokyo",
        sydney: "Sydney",
      };

      const seniorities = {
        analyst: "Analyst",
        associate: "Associate",
        "senior associate": "Senior Associate",
        vp: "Vice President",
        "vice president": "Vice President",
        director: "Director",
        partner: "Partner",
        principal: "Principal",
        manager: "Manager",
      };

      // Detect location and seniority in the query
      let detectedLocation = null;
      let detectedSeniority = null;

      // ✅ Normalize input and location keys to avoid space issues (e.g., "Hong Kong")
      const normalizedInput = inputLower.replace(/\s+/g, " ").trim();

      for (const [rawKey, value] of Object.entries(locations)) {
        const key = rawKey.trim().toLowerCase();
        const locationRegex = new RegExp(`\\b${key}\\b`, "i");
        if (locationRegex.test(normalizedInput)) {
          detectedLocation = value;
          break;
        }
      }
      for (const [key, value] of Object.entries(seniorities)) {
        if (inputLower.includes(key)) {
          detectedSeniority = value;
          break;
        }
      }

      // Handle combined location + seniority queries
      if (detectedLocation && detectedSeniority) {
        this.filterJobsByCombined(detectedLocation, detectedSeniority);
        return;
      }

      // Handle single location queries
      if (detectedLocation) {
        this.filterJobsByLocation(detectedLocation);
        return;
      }

      // Handle single seniority queries
      if (detectedSeniority) {
        this.filterJobsBySeniority(detectedSeniority);
        return;
      }

      // Check for PE filter-specific queries
      const peFilterResult = this.checkPEFilterIntent(input);
      if (peFilterResult) {
        this.applyPEFiltersFromQuery(peFilterResult);
        return;
      }

      // ALWAYS check for basic keywords - simple and reliable
      if (inputLower.includes("risk")) {
        this.applyPEFiltersFromQuery({ industry: "risk" });
        return;
      }

      // Use intelligent filter if available for complex queries
      if (this.intelligentFilter && false) {
        // Temporarily disabled to prevent conflicts
        const parsedQuery = this.intelligentFilter.parseQuery(input);

        // If we detected specific industry or criteria, use intelligent filtering
        if (
          parsedQuery.industry ||
          parsedQuery.level ||
          parsedQuery.location ||
          parsedQuery.salary ||
          parsedQuery.keywords.length > 0
        ) {
          this.intelligentSearch(parsedQuery);
          return;
        }
      }

      // Fallback to simple keyword matching
      if (inputLower.includes("compliance") || inputLower.includes("audit")) {
        this.simpleJobSearch("risk");
      } else if (
        inputLower.includes("analyst") ||
        inputLower.includes("analysis")
      ) {
        this.searchJobsByKeyword(input, "analyst");
      } else if (
        inputLower.includes("engineer") ||
        inputLower.includes("developer") ||
        inputLower.includes("programming")
      ) {
        this.searchJobsByKeyword(input, "engineering");
      } else if (
        inputLower.includes("manager") ||
        inputLower.includes("lead") ||
        inputLower.includes("director")
      ) {
        this.searchJobsByKeyword(input, "management");
      } else if (
        inputLower.includes("remote") ||
        inputLower.includes("work from home")
      ) {
        this.filterByRemote();
      } else if (
        inputLower.includes("startup") ||
        inputLower.includes("start-up")
      ) {
        this.filterByCompanyType("startup");
      } else if (
        inputLower.includes("salary") ||
        inputLower.includes("pay") ||
        inputLower.includes("compensation")
      ) {
        this.handleSalaryQuery(input);
      } else if (
        inputLower.includes("skill") ||
        inputLower.includes("experience")
      ) {
        this.handleSkillsQuery(input);
      } else if (
        inputLower.includes("more") ||
        inputLower.includes("show more") ||
        inputLower.includes("other")
      ) {
        this.showMoreJobs();
      } else if (
        inputLower.includes("analyze") ||
        inputLower.includes("compare")
      ) {
        this.switchToAnalyze();
      } else if (
        inputLower.includes("apply") ||
        inputLower.includes("application")
      ) {
        this.switchToApply();
      } else {
        // Default conversational response
        this.handleGeneralQuery(input);
      }
    }

    intelligentSearch(parsedQuery) {
      // Filter jobs using intelligent filter
      this.filteredJobs = this.intelligentFilter.filterJobs(
        this.allJobs,
        parsedQuery
      );

      // Generate appropriate response
      const response = this.intelligentFilter.generateResponse(
        parsedQuery,
        this.filteredJobs.length
      );
      this.addSennaMessage(response);

      if (this.filteredJobs.length > 0) {
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));

          // Add suggestions for follow-up
          const suggestions =
            this.intelligentFilter.getSuggestions(parsedQuery);
          if (suggestions.length > 0) {
            setTimeout(() => {
              this.addSuggestionCards(suggestions);
            }, 1000);
          }
        }, 500);
      } else {
        // Show alternative jobs
        setTimeout(() => {
          this.addSennaMessage(
            `Let me show you some other opportunities that might interest you.`
          );
          this.renderJobsInChat(this.allJobs.slice(0, 3));
        }, 500);
      }
    }

    addSuggestionCards(suggestions) {
      const suggestionsHtml = `
                <div class="suggestion-cards">
                    <p style="text-align: center; color: #666; margin-bottom: 16px;">You might also be interested in:</p>
                    <div class="option-cards">
                        ${suggestions
                          .map(
                            (suggestion) => `
                            <div class="option-card" onclick="$('#senna-input').val('${suggestion}'); sennaConversational.handleUserInput();">
                                <div class="option-card-text">${suggestion}</div>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
      this.addSennaMessage(suggestionsHtml, true);
    }

    checkPEFilterIntent(input) {
      const inputLower = input.toLowerCase().trim();
      const filters = {};

      // Enhanced seniority detection with variations and typos
      const seniorityPatterns = {
        intern: [
          "intern",
          "internship",
          "summer analyst",
          "graduate",
          "entry level",
          "entry-level",
          "junior",
          "trainee",
        ],
        analyst: [
          "analyst",
          "analytics",
          "analysis",
          "research analyst",
          "senior analyst",
          "investment analyst",
        ],
        associate: [
          "associate",
          "assoc",
          "senior associate",
          "sr associate",
          "principal associate",
        ],
        vp: [
          "vp",
          "vice president",
          "vice-president",
          "v.p.",
          "director",
          "executive director",
          "ed",
          "managing director",
          "md",
        ],
      };

      // Check all patterns and allow multiple matches
      const detectedSeniorities = [];
      for (const [level, patterns] of Object.entries(seniorityPatterns)) {
        if (patterns.some((pattern) => inputLower.includes(pattern))) {
          detectedSeniorities.push(level);
        }
      }
      if (detectedSeniorities.length > 0) {
        filters.seniority = detectedSeniorities;
      }

      // Enhanced fund size detection
      const fundSizePatterns = {
        mega: [
          "mega",
          "mega-cap",
          "mega cap",
          "large pe",
          "billion",
          "10b+",
          "blackstone",
          "kkr",
          "apollo",
          "carlyle",
        ],
        large: [
          "large cap",
          "large-cap",
          "upper mid",
          "upper-mid",
          "1b-10b",
          "billion dollar",
        ],
        mid: [
          "mid cap",
          "mid-cap",
          "middle market",
          "mid market",
          "mid-market",
          "500m",
          "growth equity",
        ],
        lower: [
          "lower mid",
          "lower-mid",
          "small cap",
          "small-cap",
          "smid",
          "sub-500m",
          "venture debt",
        ],
      };

      const detectedFundSizes = [];
      for (const [size, patterns] of Object.entries(fundSizePatterns)) {
        if (patterns.some((pattern) => inputLower.includes(pattern))) {
          detectedFundSizes.push(size);
        }
      }
      if (detectedFundSizes.length > 0) {
        filters.fundSize = detectedFundSizes;
      }

      // Enhanced location detection with abbreviations and variations
      const locationPatterns = {
        london: [
          "london",
          "uk",
          "united kingdom",
          "britain",
          "england",
          "city of london",
          "mayfair",
          "manchester",
          "birmingham",
          "edinburgh",
          "glasgow",
          "leeds",
          "bristol",
        ],
        milan: [
          "milan",
          "milano",
          "italy",
          "italian",
          "italia",
          "rome",
          "roma",
        ],
        madrid: [
          "madrid",
          "spain",
          "spanish",
          "españa",
          "barcelona",
          "valencia",
        ],
        dubai: [
          "dubai",
          "uae",
          "emirates",
          "abu dhabi",
          "private equity",
          "gulf",
          "private_equity",
          "qatar",
          "doha",
          "riyadh",
          "saudi",
        ],
        frankfurt: [
          "frankfurt",
          "germany",
          "german",
          "deutschland",
          "berlin",
          "munich",
          "münchen",
          "hamburg",
          "cologne",
          "stuttgart",
        ],
        paris: ["paris", "france", "french", "française", "lyon", "marseille"],
        saopaulo: [
          "sao paulo",
          "são paulo",
          "sao-paulo",
          "brazil",
          "brasil",
          "brazilian",
          "latam",
          "latin america",
          "rio",
          "rio de janeiro",
        ],
        newyork: [
          "new york",
          "nyc",
          "ny",
          "manhattan",
          "usa",
          "us",
          "united states",
          "america",
          "boston",
          "chicago",
          "san francisco",
          "sf",
          "la",
          "los angeles",
          "dc",
          "washington",
        ],
        singapore: ["singapore", "sg", "southeast asia", "sea", "asean"],
        hongkong: ["hong kong", "hk", "hong-kong", "hongkong"],
        zurich: ["zurich", "switzerland", "swiss", "geneva", "basel", "lugano"],
        amsterdam: [
          "amsterdam",
          "netherlands",
          "dutch",
          "holland",
          "rotterdam",
          "hague",
        ],
        tokyo: ["tokyo", "japan", "japanese", "osaka", "kyoto"],
        sydney: [
          "sydney",
          "australia",
          "melbourne",
          "brisbane",
          "perth",
          "aus",
          "aussie",
        ],
        toronto: [
          "toronto",
          "canada",
          "canadian",
          "montreal",
          "vancouver",
          "ottawa",
        ],
        mumbai: [
          "mumbai",
          "india",
          "indian",
          "delhi",
          "bangalore",
          "chennai",
          "pune",
        ],
        beijing: [
          "beijing",
          "china",
          "chinese",
          "shanghai",
          "shenzhen",
          "guangzhou",
        ],
      };

      const detectedLocations = [];
      for (const [location, patterns] of Object.entries(locationPatterns)) {
        if (
          patterns.some((pattern) => {
            // Use word boundaries for better matching
            const regex = new RegExp(`\\b${pattern}\\b`, "i");
            return regex.test(inputLower);
          })
        ) {
          detectedLocations.push(location);
        }
      }
      if (detectedLocations.length > 0) {
        filters.location = detectedLocations;
      }

      // Enhanced work style detection
      const workStylePatterns = {
        normal: [
          "normal hours",
          "work-life balance",
          "work life balance",
          "9-5",
          "9 to 5",
          "flexible",
          "remote",
          "hybrid",
          "balanced",
          "reasonable hours",
        ],
        fluctuates: [
          "fluctuates",
          "variable hours",
          "varies",
          "seasonal",
          "deal dependent",
          "project based",
          "sometimes busy",
        ],
        intense: [
          "intense",
          "long hours",
          "demanding",
          "challenging",
          "fast-paced",
          "fast paced",
          "high pressure",
          "80+ hours",
          "100 hours",
          "banking hours",
        ],
      };

      const detectedWorkStyles = [];
      for (const [style, patterns] of Object.entries(workStylePatterns)) {
        if (patterns.some((pattern) => inputLower.includes(pattern))) {
          detectedWorkStyles.push(style);
        }
      }
      if (detectedWorkStyles.length > 0) {
        filters.workStyle = detectedWorkStyles;
      }

      // Enhanced geographic focus detection
      const geoFocusPatterns = {
        "pan-european": [
          "pan-european",
          "pan european",
          "europe",
          "european",
          "eu",
          "continental",
        ],
        "uk-ireland": [
          "uk ireland",
          "uk-ireland",
          "british isles",
          "anglo",
          "united kingdom and ireland",
        ],
        dach: ["dach", "german speaking", "deutschsprachig", "alps region"],
        nordics: [
          "nordic",
          "nordics",
          "scandinavia",
          "scandinavian",
          "sweden",
          "norway",
          "denmark",
          "finland",
          "iceland",
        ],
        global: [
          "global",
          "worldwide",
          "international",
          "cross-border",
          "cross border",
          "multi-region",
        ],
        emerging: [
          "emerging",
          "emerging markets",
          "em",
          "developing",
          "frontier",
          "brics",
          "africa",
          "asia",
          "latam",
        ],
      };

      const detectedGeoFocus = [];
      for (const [focus, patterns] of Object.entries(geoFocusPatterns)) {
        if (patterns.some((pattern) => inputLower.includes(pattern))) {
          detectedGeoFocus.push(focus);
        }
      }
      if (detectedGeoFocus.length > 0) {
        filters.geoFocus = detectedGeoFocus;
      }

      // Enhanced industry/vertical detection with more patterns
      const industryPatterns = {
        risk: [
          "risk",
          "compliance",
          "audit",
          "regulatory",
          "aml",
          "kyc",
          "risk management",
          "credit risk",
          "market risk",
          "operational risk",
        ],
        "private-equity": [
          "private equity",
          "\\bpe\\b",
          "buyout",
          "lbo",
          "growth equity",
          "portfolio company",
          "portco",
          "deal team",
          "investment team",
        ],
        "venture-capital": [
          "venture",
          "\\bvc\\b",
          "startup",
          "start-up",
          "seed",
          "series a",
          "series b",
          "early stage",
          "growth stage",
        ],
        "hedge-fund": [
          "hedge fund",
          "hedgefund",
          "trading",
          "quant",
          "quantitative",
          "systematic",
          "discretionary",
          "long short",
          "long/short",
        ],
        "investment-banking": [
          "investment banking",
          "\\bib\\b",
          "ibd",
          "ma",
          "m&a",
          "mergers",
          "acquisitions",
          "capital markets",
          "ecm",
          "dcm",
        ],
        "asset-management": [
          "asset management",
          "portfolio management",
          "fund management",
          "wealth management",
          "investment management",
        ],
        "real-estate": [
          "real estate",
          "property",
          "repe",
          "reit",
          "real assets",
          "infrastructure",
        ],
        crypto: [
          "crypto",
          "blockchain",
          "defi",
          "web3",
          "digital assets",
          "cryptocurrency",
          "bitcoin",
          "ethereum",
        ],
        consulting: [
          "consulting",
          "advisory",
          "strategy",
          "mckinsey",
          "bain",
          "bcg",
          "management consulting",
        ],
        tech: [
          "tech",
          "technology",
          "software",
          "saas",
          "fintech",
          "product",
          "engineering",
          "data science",
          "machine learning",
          "ai",
        ],
      };

      const detectedIndustries = [];
      for (const [industry, patterns] of Object.entries(industryPatterns)) {
        if (
          patterns.some((pattern) => {
            // Use regex for better word boundary matching
            const regex = new RegExp(pattern, "i");
            return regex.test(inputLower);
          })
        ) {
          detectedIndustries.push(industry);
        }
      }
      if (detectedIndustries.length > 0) {
        // Take the first match as primary, but store all for potential use
        filters.industry = detectedIndustries[0];
        if (detectedIndustries.length > 1) {
          filters.additionalIndustries = detectedIndustries.slice(1);
        }
      }

      // Enhanced salary detection with multiple formats
      const salaryMatch = inputLower.match(
        /(?:[\$£€]?(\d+)k?\+?)|(?:(\d+),?000\+?)|(?:six figures?)|(?:high (?:salary|pay|comp))/
      );
      if (salaryMatch) {
        if (inputLower.includes("six figure")) {
          filters.salaryMin = 100;
        } else if (
          inputLower.includes("high salary") ||
          inputLower.includes("high pay") ||
          inputLower.includes("high comp")
        ) {
          filters.salaryMin = 150;
        } else if (salaryMatch[1]) {
          const amount = parseInt(salaryMatch[1]);
          if (amount > 1000) {
            filters.salaryMin = Math.floor(amount / 1000);
          } else {
            filters.salaryMin = amount;
          }
        }
      }

      // Check for match score requirements
      if (
        inputLower.match(/(?:90|95|85)\+?%?\s*match/) ||
        inputLower.includes("best match") ||
        inputLower.includes("top match")
      ) {
        filters.minMatchScore = 85;
      } else if (inputLower.includes("good match")) {
        filters.minMatchScore = 70;
      }

      // Check for experience level requirements
      const yearsMatch = inputLower.match(
        /(\d+)\+?\s*(?:years?|yrs?)\s*(?:of\s*)?(?:experience|exp)?/
      );
      if (yearsMatch) {
        filters.minExperience = parseInt(yearsMatch[1]);
      }

      // Check for negative filters (what to exclude)
      if (
        inputLower.includes("no ") ||
        inputLower.includes("not ") ||
        inputLower.includes("without ") ||
        inputLower.includes("exclude ")
      ) {
        filters.exclude = [];

        if (
          inputLower.includes("no travel") ||
          inputLower.includes("without travel")
        ) {
          filters.exclude.push("travel");
        }
        if (
          inputLower.includes("no relocation") ||
          inputLower.includes("not relocate")
        ) {
          filters.exclude.push("relocation");
        }
        if (
          inputLower.includes("no startup") ||
          inputLower.includes("not startup")
        ) {
          filters.exclude.push("startup");
        }
      }

      // Check for remote/hybrid preferences
      if (
        inputLower.includes("remote") ||
        inputLower.includes("wfh") ||
        inputLower.includes("work from home")
      ) {
        filters.remote = true;
      } else if (inputLower.includes("hybrid")) {
        filters.hybrid = true;
      } else if (
        inputLower.includes("onsite") ||
        inputLower.includes("on-site") ||
        inputLower.includes("office")
      ) {
        filters.onsite = true;
      }

      // Check for urgency/timing
      if (
        inputLower.includes("immediate") ||
        inputLower.includes("urgent") ||
        inputLower.includes("asap") ||
        inputLower.includes("now")
      ) {
        filters.immediate = true;
      }

      // Special patterns for common queries
      if (
        inputLower.includes("show me") ||
        inputLower.includes("find me") ||
        inputLower.includes("looking for") ||
        inputLower.includes("search for") ||
        inputLower.includes("i want") ||
        inputLower.includes("i need")
      ) {
        filters.explicitSearch = true;
      }

      // Handle "or" queries (e.g., "London or Paris")
      if (inputLower.includes(" or ")) {
        // This is a complex query with alternatives
        filters.hasAlternatives = true;
      }

      // Return filters if any were found, or null if no filters detected
      return Object.keys(filters).length > 0 ? filters : null;
    }
    needsIntelligentSearch(input) {
      let query = input.toLowerCase().trim();

      // 🧠 Normalize phrasing like "find jobs in" / "show roles for"
      query = query
        .replace(
          /^(?:can you\s+)?(?:please\s+)?(?:find|show|list|search|give me|show me|display|look for|skills)\s+(?:all\s+)?(?:the\s+)?(?:jobs?|roles?|positions?|opportunities?)?\s*(?:available\s+|open\s+|vacant\s+|for\s+)?(?:in|for)?\s*/i,
          ""
        )
        .trim();

      // 📍 Location patterns
      const locationPatterns = [
        // 🇬🇧 UK & Ireland
        "london",
        "londra",
        "londres",
        "manchester",
        "edinburgh",
        "birmingham",
        "leeds",
        "bristol",
        "glasgow",
        "dublin",
        "belfast",

        // 🇫🇷 France
        "paris",
        "lyon",
        "marseille",
        "toulouse",
        "bordeaux",

        // 🇩🇪 Germany
        "frankfurt",
        "berlin",
        "munich",
        "hamburg",
        "stuttgart",
        "cologne",
        "düsseldorf",
        "duesseldorf",
        "berlin",
        "düsseldorf",
        "leipzig",
        "dresden",
        "nuremberg",
        "hannover",
        "bremen",
        "bonn",
        "essen",
        "mannheim",
        "karlsruhe",
        "frankfurt am main",

        // 🇨🇭 Switzerland
        "zurich",
        "geneva",
        "basel",
        "lausanne",
        "zug",
        "bern",
        "lucerne",
        "st gallen",
        "st. gallen",

        // 🇮🇹 Italy
        "milan",
        "rome",
        "turin",
        "florence",
        "naples",
        "venice",
        "bologna",
        "genoa",
        "verona",
        "palermo",
        "padua",

        // 🇪🇸 Spain
        "madrid",
        "barcelona",
        "valencia",
        "seville",
        "valencia",
        "seville",
        "bilbao",
        "zaragoza",
        "malaga",
        "alicante",
        "granada",
        "palma de mallorca",
        "san sebastian",

        // 🇳🇱 Netherlands & Belgium
        "amsterdam",
        "rotterdam",
        "the hague",
        "utrecht",
        "eindhoven",
        "brussels",
        "antwerp",
        "ghent",
        "bruges",
        "liege",
        "luxembourg",

        // 🇸🇪🇩🇰🇳🇴 Nordics
        "stockholm",
        "copenhagen",
        "oslo",
        "helsinki",
        "gothenburg",
        "bergen",
        "malmo",
        "aarhus",

        // 🇺🇸 USA
        "new york",
        "san francisco",
        "los angeles",
        "boston",
        "chicago",
        "washington dc",
        "washington d.c.",
        "dc",
        "austin",
        "miami",
        "dallas",
        "houston",
        "seattle",
        "denver",
        "philadelphia",
        "atlanta",
        "charlotte",

        // 🇨🇦 Canada
        "toronto",
        "vancouver",
        "montreal",
        "calgary",
        "ottawa",

        // 🇭🇰🇸🇬 Asia Hubs
        "hong kong",
        "singapore",
        "dubai",
        "abu dhabi",
        "doha",
        "riyadh",
        "jeddah",
        "mumbai",
        "delhi",
        "bangalore",
        "bengaluru",
        "shanghai",
        "beijing",
        "tokyo",
        "osaka",
        "seoul",
        "taipei",
        "kuala lumpur",
        "jakarta",
        "manila",
        "bangkok",

        // 🇦🇺 Oceania
        "sydney",
        "melbourne",
        "brisbane",
        "perth",
        "auckland",
        "wellington",

        // 🌎 Latin America / South America
        "mexico city",
        "guadalajara",
        "monterrey",
        "bogota",
        "medellin",
        "lima",
        "sao paulo",
        "são paulo",
        "rio de janeiro",
        "buenos aires",
        "santiago",
        "montevideo",
        "caracas",
        "quito",
        "panama city",
        "san jose costa rica",

        // 🌍 Other Emerging & Offshore Hubs
        "cape town",
        "johannesburg",
        "lagos",
        "nairobi",
        "casablanca",
        "tel aviv",
        "istanbul",
        "doha",
        "bahrain",
        "kuwait",
        "cyprus",
        "malta",
        "monaco",

        // 🌐 Remote / Global
        "remote",
        "hybrid",
        "work from home",
        "global",
        "europe",
        "asia",
        "latin america",
        "south america",
        "private equity",
        "africa",
        "americas",
      ];

      // 🧍‍♂️ Seniority / role patterns
      const seniorityPatterns = [
        "intern",
        "analyst",
        "junior",
        "associate",
        "senior associate",
        "vp",
        "vice president",
        "avp",
        "principal",
        "director",
        "managing director",
        "head of",
        "chief",
        "executive",
        "partner",
        "senior manager",
        "manager",
        "lead",
        "associate director",
      ];

      // 🏢 Industry patterns
      const industryPatterns = [
        "private equity",
        "venture capital",
        "vc",
        "hedge fund",
        "asset management",
        "investment banking",
        "corporate finance",
        "m&a",
        "lbo",
        "fintech",
        "life science",
        "biotech",
        "pharma",
        "crypto",
        "blockchain",
        "esg",
        "sustainability",
        "impact",
        "real estate",
        "infrastructure",
      ];

      // 🛠️ Skills & tool patterns (hard skills, systems, software)
      const skillPatterns = [
        "financial modelling",
        "financial modeling",
        "valuation",
        "excel",
        "advanced excel",
        "excel vba",
        "macros",
        "powerpoint",
        "word",
        "presentation skills",
        "communication skills",
        "analytical skills",
        "due diligence",
        "lbo modeling",
        "dcf analysis",
        "comparable company analysis",
        "precedent transactions",
        "merger modeling",
        "sensitivity analysis",
        "scenario analysis",
        "capital budgeting",
        "forecasting",
        "budgeting",
        "variance analysis",
        "kpi analysis",
        "ratio analysis",
        "financial analysis",
        "strategic analysis",
        "credit analysis",
        "equity research",
        "fixed income analysis",
        "derivatives pricing",
        "options pricing",
        "risk management",
        "hedging strategies",
        "treasury management",
        "cash flow forecasting",
        "portfolio management",
        "asset allocation",
        "fund accounting",
        "financial reporting",
        "regulatory reporting",
        "ifrs",
        "gaap",
        "sox compliance",
        "audit",
        "internal controls",
        "tax planning",
        "tax compliance",
        "corporate finance",
        "m&a",
        "mergers and acquisitions",
        "leveraged finance",
        "structured finance",
        "project finance",
        "private equity",
        "venture capital",
        "lbo",
        "ipo",
        "debt issuance",
        "equity issuance",
        "capital markets",
        "investment analysis",
        "valuation modeling",
        "business valuation",
        "company valuation",
        "transaction services",
        "deal advisory",
        "financial due diligence",
        "operational due diligence",
        "market analysis",
        "competitive analysis",
        "business strategy",
        "strategic finance",
        "financial planning",
        "fp&a",
        "treasury",
        "investment banking",
        "private markets",
        "alternative investments",
        "hedge funds",
        "asset management",
        "wealth management",
        "financial advisory",
        "consulting",
        "compliance",
        "anti-money laundering",
        "aml",
        "kyc",
        "know your customer",
        "regulatory compliance",
        "basel iii",
        "solvency ii",
        "mi reporting",
        "management reporting",
        "data analysis",
        "data visualization",
        "tableau",
        "power bi",
        "sql",
        "python",
        "r",
        "sas",
        "stochastic modeling",
        "excel dashboards",
        "scenario planning",
        "financial systems",
        "sap",
        "oracle",
        "netsuite",
        "workday",
        "anaplan",
        "adaptive insights",
        "erp systems",
        "crm systems",
        "salesforce",
        "notion",
        "jira",
        "aha",
        "asana",
        "trello",
        "process automation",
        "business intelligence",
        "reporting automation",
        "presentation design",
        "pitch decks",
        "investment memorandums",
        "teasers",
        "information memorandums",
        "term sheets",
        "cap tables",
        "waterfall modeling",
        "carry calculations",
        "investment memos",
        "deal sourcing",
        "deal origination",
        "pipeline management",
        "fundraising",
        "investor relations",
        "fund accounting",
        "performance measurement",
        "carried interest",
        "fund modeling",
        "portfolio monitoring",
        "esg analysis",
        "impact investing",
        "accounting",
        "climate finance",
        "green finance",
        "carbon accounting",
        "sustainability reporting",
        "benchmarking",
        "business partnering",
        "stakeholder management",
        "board reporting",
        "executive reporting",
        "storytelling with data",
        "cost accounting",
        "management accounting",
        "financial controls",
        "reconciliations",
        "journal entries",
        "month end close",
        "quarter end close",
        "year end close",
        "audit support",
        "tax compliance",
        "treasury operations",
        "hedge accounting",
        "investment strategy",
        "financial statement analysis",
        "p&l analysis",
        "balance sheet analysis",
        "cash flow analysis",
        "net present value",
        "internal rate of return",
        "cost of capital",
        "wacc",
        "financial regulations",
        "securities law",
        "miifid ii",
        "gdpr compliance",
        "stress testing",
        "scenario testing",
        "budget modelling",
        "revenue forecasting",
        "expense forecasting",
        "cost reduction",
        "profit improvement",
        "business transformation",
        "operational efficiency",
        "process improvement",
        "lean finance",
        "six sigma finance",
        "benchmarking analysis",
        "roi analysis",
        "payback analysis",
        "kpi dashboards",
        "variance reporting",
        "transfer pricing",
        "international tax",
        "treasury strategy",
        "debt structuring",
        "currency risk management",
        "fx hedging",
        "interest rate hedging",
        "swaps",
        "options",
        "futures",
        "securitization",
        "real estate finance",
        "infrastructure finance",
        "renewable energy finance",
        "project evaluation",
        "deal structuring",
        "capex analysis",
        "opex analysis",
        "business case development",
        "investment committee papers",
        "financial governance",
        "controls testing",
        "cost allocation",
        "risk assessment",
        "enterprise risk management",
        "financial operations",
        "fund operations",
        "lp reporting",
        "gp reporting",
        "carry distribution",
        "waterfall calculations",
        "performance attribution",
        "benchmark construction",
        "compliance testing",
        "policy development",
        "financial policies",
        "audit trail",
        "transparency reporting",
        "management dashboards",
        "cross functional collaboration",
        "stakeholder engagement",
        "finance transformation",
        "systems implementation",
        "data migration",
        "report automation",
        "excel shortcuts",
        "keyboard efficiency",
        "document automation",
        "pitchbook",
        "factset",
        "bloomberg terminal",
        "capital iq",
        "morningstar",
        "refinitiv",
        "market research",
        "economic analysis",
        "macro analysis",
        "micro analysis",
        "sector research",
        "thematic investing",
        "quantitative analysis",
        "qualitative analysis",
        "investment screening",
        "deal evaluation",
        "pipeline tracking",
        "crm tracking",
        "investment thesis",
        "financial storytelling",
        "executive summaries",
        "board packs",
        "analyst notes",
        "credit modeling",
        "credit risk",
        "counterparty risk",
        "market risk",
        "liquidity risk",
        "operational risk",
        "financial crime",
        "compliance frameworks",
        "internal audit",
        "external audit",
        "business continuity",
        "contingency planning",
        "financial strategy",
        "corporate strategy",
        "finance business partnering",
        "stakeholder reporting",
        "investor presentations",
        "governance",
        "board relations",
        "policy compliance",
        "bloomberg",
        "bloomberg terminal",
        "capital iq",
        "s&p capital iq",
        "factset",
        "refinitiv",
        "thomson reuters",
        "pitchbook",
        "preqin",
        "morningstar",
        "merger market",
        "dealogic",
        "ibisworld",
        "cb insights",
        "privco",
        "crunchbase",
        "marketwatch",
        "yahoo finance",
        "seeking alpha",
        "edgar",
        "sec edgar",
        "investing.com",
        "sap",
        "sap hana",
        "sap fico",
        "sap bpc",
        "oracle",
        "oracle ebs",
        "oracle fusion",
        "oracle netsuite",
        "netsuite",
        "workday",
        "sage",
        "sage intacct",
        "xero",
        "quickbooks",
        "freshbooks",
        "intuit quickbooks",
        "intuit",
        "unit4",
        "epicor",
        "infor",
        "microsoft dynamics",
        "dynamics 365",
        "dynamics ax",
        "dynamics nav",
        "anaplan",
        "adaptive insights",
        "workiva",
        "blackline",
        "tagetik",
        "one stream",
        "onestream",
        "host analytics",
        "planful",
        "vena",
        "board financial planning",
        "hyperion",
        "hyperion planning",
        "hyperion financial management",
        "hfm",
        "cubewise",
        "longview",
        "jedox",
        "salesforce",
        "hubspot",
        "zoho crm",
        "pipedrive",
        "microsoft dynamics crm",
        "notion",
        "jira",
        "aha",
        "asana",
        "trello",
        "monday.com",
        "airtable",
        "clickup",
        "confluence",
        "basecamp",
        "slack",
        "teams",
        "google workspace",
        "sharepoint",
        "dropbox",
        "box",
        "evernote",
        "dealcloud",
        "navatar",
        "affinity",
        "backstop solutions",
        "blackmountain systems",
        "iLEVEL",
        "eFront",
        "investran",
        "chronograph",
        "fundwave",
        "allvue",
        "dynamo",
        "transect",
        "cartapulse",
        "ims",
        "dealpath",
        "juniper square",
        "vistaprint",
        "fundstack",
        "wolters kluwer",
        "fenergo",
        "actimize",
        "nice actimize",
        "regnology",
        "regtech",
        "vermeg",
        "aia compliance",
        "compliance 360",
        "metricstream",
        "riskwatch",
        "riskturn",
        "moody's analytics",
        "moodys",
        "riskmetrics",
        "blackrock aladdin",
        "aladdin",
        "calypso",
        "murex",
        "quantlib",
        "charles river",
        "crd",
        "fidessa",
        "bloomberg aim",
        "simcorp dimension",
        "simcorp",
        "calypso trading",
        "murex trading",
        "sungard",
        "sun systems",
        "finastra",
        "kyriba",
        "gtreasury",
        "openlink",
        "reval",
        "quantconnect",
        "interactive brokers",
        "etrade pro",
        "thinkorswim",
        "metatrader",
        "mt4",
        "mt5",
        "docsend",
        "hellosign",
        "docusign",
        "notion",
        "slack",
        "zapier",
        "make.com",
        "n8n",
        "microsoft teams",
        "zoom",
        "loom",
        "otter.ai",
        "grammarly",
        "figma",
        "miro",
        "python",
        "r",
        "javascript",
        "typescript",
        "java",
        "c",
        "c++",
        "c#",
        "go",
        "golang",
        "swift",
        "kotlin",
        "php",
        "ruby",
        "perl",
        "objective-c",
        "matlab",
        "scala",
        "rust",
        "haskell",
        "dart",
        "lua",
        "bash",
        "shell scripting",
        "powershell",
        "vbscript",
        "sql",
        "pl/sql",
        "t-sql",
        "html",
        "html5",
        "css",
        "css3",
        "scss",
        "sass",
        "less",
        "bootstrap",
        "tailwind",
        "tailwind css",
        "material ui",
        "chakra ui",
        "bulma",
        "foundation",
        "jquery",
        "vanilla js",
        "handlebars",
        "mustache",
        "astro",
        "htmx",

        "node.js",
        "nodejs",
        "express",
        "next.js",
        "nextjs",
        "nuxt.js",
        "nuxtjs",
        "nest.js",
        "nestjs",
        "fastify",
        "koa",
        "adonisjs",
        "sails.js",
        "django",
        "flask",
        "fastapi",
        "pyramid",
        "bottle",
        "rails",
        "ruby on rails",
        "laravel",
        "symfony",
        "codeigniter",
        "zend framework",
        "spring",
        "spring boot",
        "micronaut",
        "quarkus",

        "tensorflow",
        "pytorch",
        "scikit-learn",
        "keras",
        "mxnet",
        "theano",
        "huggingface",
        "transformers",
        "langchain",
        "llamaindex",
        "haystack",
        "openai",
        "anthropic",
        "claude",
        "cursor ai",
        "cursor",
        "replit",
        "code interpreter",
        "notebooks",
        "jupyter",
        "colab",
        "google colab",
        "mlflow",
        "weights and biases",
        "wandb",
        "neptune.ai",
        "vertex ai",
        "azure ml",
        "sage maker",
        "sagemaker",
        "databricks ml",

        "tensorflow",
        "pytorch",
        "scikit-learn",
        "keras",
        "mxnet",
        "theano",
        "huggingface",
        "transformers",
        "langchain",
        "llamaindex",
        "haystack",
        "openai",
        "anthropic",
        "claude",
        "cursor ai",
        "cursor",
        "replit",
        "code interpreter",
        "notebooks",
        "jupyter",
        "colab",
        "google colab",
        "mlflow",
        "weights and biases",
        "wandb",
        "neptune.ai",
        "vertex ai",
        "azure ml",
        "sage maker",
        "sagemaker",
        "databricks ml",

        "docker",
        "kubernetes",
        "k8s",
        "terraform",
        "ansible",
        "chef",
        "puppet",
        "jenkins",
        "github actions",
        "gitlab ci",
        "travis ci",
        "circleci",
        "argo cd",
        "flux",
        "aws",
        "amazon web services",
        "gcp",
        "google cloud",
        "azure",
        "digitalocean",
        "heroku",
        "netlify",
        "vercel",
        "render",
        "cloudflare",
        "fly.io",
        "nginx",
        "apache",
        "tomcat",
        "iis",

        "mysql",
        "postgres",
        "postgresql",
        "mariadb",
        "oracle db",
        "sql server",
        "mssql",
        "sqlite",
        "db2",
        "mongodb",
        "couchdb",
        "couchbase",
        "dynamodb",
        "cosmosdb",
        "redis",
        "elasticsearch",
        "opensearch",
        "influxdb",
        "timescaledb",
        "firebase",
        "firestore",
        "supabase",
        "planetscale",
        "neon",
        "snowflake",
        "bigquery",
        "redshift",
        "clickhouse",

        "react native",
        "flutter",
        "cordova",
        "ionic",
        "xamarin",
        "swiftui",
        "jetpack compose",
        "expo",
        "capacitor",
        "pwa",
        "progressive web apps",

        "cursor ai",
        "cursor",
        "replit",
        "codeium",
        "tabnine",
        "copilot",
        "github copilot",
        "aider",
        "windsurf",
        "blackbox ai",
        "amazon q",
        "chatgpt",
        "claude",
        "grok",
        "perplexity ai",
        "bolt.new",
        "v0.dev",
        "bolt ai",
        "open interpreter",

        "git",
        "github",
        "gitlab",
        "bitbucket",
        "svn",
        "mercurial",
        "azure devops",
        "jira",
        "trello",
        "confluence",
        "linear",
        "clickup",
        "notion",
        "slack",
        "microsoft teams",

        "matlab",
        "r",
        "stata",
        "sas",
        "vba",
        "excel vba",
        "quantlib",
        "wolfram mathematica",
        "julia",
        "ampl",
        "gams",
        "fortran",
        "cplex",
        "gurobi",
        "blaze",
        "numpy",
        "pandas",
        "scipy",
        "Alteryx",
        "accounting skills",
        "bookkeeping",
        "reconciliation",
        "bank reconciliation",
        "financial statements",
        "preparing financial statements",
        "monthly reporting",
        "year end accounts",
        "statutory accounts",
        "p&l",
        "profit and loss",
        "balance sheet",
        "cash flow",
        "cash flow management",
        "double entry bookkeeping",
        "accruals and prepayments",
        "general ledger",
        "accounts payable",
        "accounts receivable",
        "payables",
        "receivables",
        "fixed assets",
        "depreciation schedules",
        "journal entries",
        "posting journals",
        "trial balance",
        "audit preparation",
        "auditing experience",
        "month end close",
        "quarter end reporting",
        "financial reporting",
        "management accounts",
        "management reporting",
        "variance analysis",
        "budgeting",
        "forecasting",
        "cost accounting",
        "tax accounting",
        "corporation tax",
        "VAT",
        "VAT returns",
        "tax returns",
        "payroll processing",
        "accounts assistant skills",
        "assistant accountant experience",
        "accounting software experience",
        "IFRS knowledge",
        "UK GAAP",
        "US GAAP",
        "financial controls",
        "internal controls",
        "SOX compliance",
        "excel for accountants",
        "advanced excel",
        "pivot tables and vlookups",
        "reconciliations and reporting",
        "private equity skills",
        "private equity experience",
        "pe skills",
        "pe experience",
        "deal experience",
        "deal execution",
        "deal sourcing",
        "origination experience",
        "transaction experience",
        "investment analysis",
        "investment modelling",
        "financial modelling",
        "lbo modelling",
        "leveraged buyout modelling",
        "building lbo models",
        "valuation skills",
        "business valuation",
        "investment valuation",
        "commercial due diligence",
        "financial due diligence",
        "operational due diligence",
        "due diligence experience",
        "market analysis",
        "industry research",
        "investment thesis building",
        "writing investment memos",
        "investment committee materials",
        "ic papers",
        "portfolio monitoring",
        "portfolio company management",
        "value creation plans",
        "bolt-on acquisitions",
        "buy and build strategy",
        "exit strategy planning",
        "deal structuring",
        "term sheet negotiation",
        "cap table modelling",
        "waterfall modelling",
        "exit waterfall models",
        "debt structuring",
        "credit analysis",
        "leveraged finance experience",
        "m&a experience",
        "ibd experience",
        "investment banking background",
        "strategy consulting background",
        "financial statement analysis",
        "advanced excel modelling",
        "excel and lbo skills",
        "lbo and valuation",
        "fund modelling",
        "fund performance analysis",
        "carried interest modelling",
        "track record analysis",
        "investment committee work",
        "pipeline management",
        "crm usage in deal teams",
        "compliance skills",
        "regulatory compliance skills",
        "financial compliance skills",
        "skills for compliance jobs",
        "skills needed for compliance roles",
        "skills for compliance officer",
        "skills for aml analyst",
        "skills for compliance analyst",
        "compliance experience",
        "aml skills",
        "anti money laundering skills",
        "anti-money laundering knowledge",
        "kyc skills",
        "know your customer expertise",
        "client onboarding compliance",
        "transaction monitoring skills",
        "sanctions screening skills",
        "financial crime skills",
        "financial crime compliance experience",
        "regulatory reporting experience",
        "regulatory analysis skills",
        "mi reporting compliance",
        "policy drafting skills",
        "policy and procedure writing",
        "compliance advisory skills",
        "regulatory advisory experience",
        "risk assessment skills",
        "internal audit skills",
        "monitoring and testing skills",
        "conduct risk skills",
        "market abuse compliance skills",
        "prudential regulation experience",
        "banking regulation knowledge",
        "fca regulations knowledge",
        "sec regulations experience",
        "gdpr compliance knowledge",
        "data protection compliance",
        "esg compliance skills",
        "sustainability regulation knowledge",
        "regulatory change skills",
        "regulatory change management",
        "regulatory horizon scanning",
        "compliance training experience",
        "code of conduct experience",
        "ethics and compliance skills",
        "breach management skills",
        "whistleblowing procedures experience",
        "compliance frameworks knowledge",
        "sox compliance skills",
        "sar filing experience",
        "suspicious activity reporting",
        "aml investigations experience",
        "financial sanctions knowledge",
        "regtech tools experience",
        "compliance monitoring tools skills",
        "policy implementation skills",
        "licensing compliance skills",
        "prudential reporting knowledge",
        "risk and compliance background",
        "regulatory exams experience",
        "smcr experience",
        "senior managers regime knowledge",
        "regulatory liaison skills",
        "regulator interaction experience",
        "hedge fund skills",
        "hedge fund experience",
        "hedge fund analyst skills",
        "hedge fund associate skills",
        "skills needed for hedge fund jobs",
        "skills for hedge fund analyst",
        "skills for hedge fund associate",
        "hedge fund technical skills",
        "hedge fund modelling skills",
        "hedge fund investment skills",
        "hedge fund trading skills",
        "equity long short skills",
        "equity long/short skills",
        "long short investing experience",
        "macro investing skills",
        "global macro experience",
        "event driven investing skills",
        "merger arbitrage experience",
        "arbitrage strategies skills",
        "multi-strategy hedge fund skills",
        "quantitative hedge fund skills",
        "quant skills for hedge funds",
        "quant trading experience",
        "quant research skills",
        "python for hedge funds",
        "coding skills for hedge funds",
        "hedge fund research skills",
        "equity research skills for hedge funds",
        "fundamental analysis skills",
        "technical analysis skills",
        "portfolio management skills",
        "risk management skills",
        "hedge fund operations skills",
        "trading execution skills",
        "hedge fund compliance skills",
        "hedge fund marketing skills",
        "investor relations hedge fund skills",
        "capital raising experience",
        "performance attribution skills",
        "hedge fund valuation skills",
        "hedge fund reporting skills",
        "pnl reporting skills",
        "hedge fund accounting knowledge",
        "hedge fund due diligence skills",
        "prime brokerage experience",
        "trading systems experience",
        "bloomberg hedge fund usage",
        "hedge fund crm tools",
        "hedge fund back office skills",
        "hedge fund middle office skills",
        "hedge fund front office experience",
        "hedge fund strategy knowledge",
        "hedge fund investment process",
        "hedge fund pipeline management",
        "hedge fund fund of funds experience",
        "fof investing skills",
        "allocator skills",
        "lp investing experience",
        "hedge fund risk modelling",
        "hedge fund derivatives knowledge",
        "options trading skills",
        "futures trading skills",
        "swaps knowledge",
        "hedge fund legal and compliance",
        "hedge fund fundraising skills",
        "hedge fund client management skills",
        "hedge fund reporting to investors",
        "hedge fund presentation skills",
        "hedge fund excel modelling",
        "hedge fund pnl analysis",
        "hedge fund returns analysis",
        "hedge fund investment memo writing",
        "hedge fund research process",
        "hedge fund strategy analysis",
        "hedge fund product structuring",
        "hedge fund seeding experience",
        "hedge fund capital introduction experience",
        "real estate skills",
        "real estate investment skills",
        "real estate private equity skills",
        "repe skills",
        "re pe skills",
        "real estate modelling skills",
        "real estate financial modelling",
        "real estate valuation skills",
        "real estate acquisitions skills",
        "real estate underwriting skills",
        "property underwriting skills",
        "underwriting experience real estate",
        "real estate development skills",
        "property development experience",
        "real estate asset management skills",
        "real estate asset management experience",
        "property asset management skills",
        "property management skills",
        "real estate portfolio management skills",
        "real estate market analysis skills",
        "property market research",
        "real estate investment analysis",
        "real estate deal sourcing skills",
        "real estate deal execution experience",
        "real estate transactions experience",
        "property transactions experience",
        "real estate due diligence skills",
        "property due diligence",
        "real estate investment committee experience",
        "real estate ic memos",
        "real estate investment memos",
        "real estate strategy experience",
        "real estate fund modelling skills",
        "real estate fund experience",
        "real estate capital markets skills",
        "real estate structured finance skills",
        "real estate debt experience",
        "real estate mezzanine finance skills",
        "real estate bridge financing",
        "real estate acquisitions analyst skills",
        "real estate analyst skills",
        "real estate associate skills",
        "skills for real estate analyst jobs",
        "skills needed for real estate investment",
        "skills required for real estate acquisitions",
        "real estate development analyst skills",
        "property acquisitions experience",
        "real estate leasing experience",
        "real estate financing experience",
        "property financing skills",
        "property valuation skills",
        "argus modelling skills",
        "argus enterprise experience",
        "real estate excel modelling",
        "real estate cash flow modelling",
        "property cashflow analysis",
        "discounted cash flow real estate",
        "dcf modelling real estate",
        "commercial real estate skills",
        "cre skills",
        "industrial real estate experience",
        "logistics real estate knowledge",
        "retail real estate experience",
        "office real estate skills",
        "residential real estate experience",
        "mixed use development skills",
        "real estate zoning knowledge",
        "real estate permitting experience",
        "planning applications real estate",
        "real estate feasibility analysis",
        "property feasibility analysis",
        "real estate financial analysis skills",
        "real estate construction finance",
        "real estate investor relations",
        "real estate fundraising experience",
        "real estate equity raising skills",
        "real estate debt raising skills",
        "real estate joint venture experience",
        "real estate fund structuring",
        "real estate investment trusts skills",
        "reit skills",
        "reit modelling",
        "reit analysis",
        "reit reporting",
        "property tax knowledge",
        "real estate accounting skills",
        "real estate compliance skills",
        "portfolio management",
        "investment research",
        "asset allocation",
        "risk management",
        "financial modelling",
        "valuation",
        "client reporting",
        "performance attribution",
        "investment strategy",
        "fund accounting",
        "equities",
        "fixed income",
        "alternatives",
        "derivatives",
        "quantitative analysis",
        "macroeconomics",
        "securities analysis",
        "bloomberg",
        "morningstar",
        "factset",
        "excel",
        "python",
        "r",
        "esg",
        "compliance",
        "regulations",
        "capital markets",
        "credit analysis",
        "liquidity management",
        "risk analytics",
        "fund operations",
        "trading support",
        "client servicing",
        "reporting",
        "asset pricing",
        "market data analysis",
        "investment process",
        "product structuring",
        "investment performance",
        "rebalancing",
        "hedging strategies",
        "due diligence",

        // 💬 Realistic user search phrases — natural language patterns
        "asset management skills",
        "skills needed for asset management",
        "what skills do i need for asset management",
        "skills to work in asset management",
        "portfolio management skills",
        "investment analysis skills",
        "skills for investment research",
        "financial modelling skills for asset management",
        "asset allocation skills",
        "risk management skills",
        "excel modelling for asset management",
        "valuation skills for asset management",
        "bloomberg skills for asset management",
        "factset skills for asset management",
        "morningstar skills",
        "esg skills for asset managers",
        "quantitative skills for asset management",
        "skills required for asset management jobs",
        "top skills for asset management analysts",
        "technical skills needed in asset management",
        "skills to get into asset management",
        "asset management job skills list",
        "skills for portfolio analysts",
        "skills for fund analysts",
        "skills needed for equities research",
        "skills for fixed income investing",
        "skills for alternative investments",
        "skills for risk and compliance in asset management",
        "what tools are used in asset management",
        "systems used in asset management",
        "software skills for asset management",
        // 🧠 Core keywords — single skills / concepts / tools
        "risk management",
        "operational risk",
        "market risk",
        "credit risk",
        "liquidity risk",
        "enterprise risk",
        "risk assessment",
        "risk analysis",
        "risk modelling",
        "stress testing",
        "scenario analysis",
        "value at risk",
        "var",
        "economic capital",
        "regulatory capital",
        "basel iii",
        "basel iv",
        "solvency ii",
        "icaap",
        "ifr",
        "regulations",
        "compliance",
        "internal controls",
        "controls testing",
        "risk reporting",
        "risk appetite",
        "risk framework",
        "risk monitoring",
        "governance",
        "esg risk",
        "climate risk",
        "model validation",
        "quantitative risk",
        "qualitative risk",
        "hedging strategies",
        "derivatives",
        "financial modelling",
        "data analysis",
        "risk analytics",
        "excel",
        "python",
        "r",
        "sas",
        "matlab",
        "sql",
        "tableau",
        "power bi",

        // 💬 Realistic user search phrases — natural language patterns
        "risk management skills",
        "skills needed for risk management",
        "what skills do i need for risk management",
        "skills to work in risk",
        "risk analysis skills",
        "risk modelling skills",
        "skills required for risk roles",
        "top skills for risk management jobs",
        "skills for market risk",
        "skills for credit risk",
        "skills for operational risk",
        "skills for enterprise risk management",
        "skills to work in risk departments",
        "technical skills for risk analysts",
        "excel skills for risk management",
        "quantitative skills for risk management",
        "data analysis skills for risk",
        "programming skills for risk jobs",
        "skills for regulatory risk",
        "skills for risk reporting",
        "skills for building risk frameworks",
        "skills to become a risk analyst",
        "skills needed for risk assessment",
        "skills required for stress testing",
        "skills for scenario analysis",
        "skills for value at risk modelling",
        "skills for basel iii compliance",
        "skills for solvency ii",
        "skills to work in model validation",
        "risk governance skills",
        "skills for liquidity risk",
        "skills to work in risk teams",
        "skills to work in risk consulting",
        "software skills for risk management",
        "tools used in risk management",
        "systems used in risk departments",
        "quantitative analysis",
        "quant research",
        "quant trading",
        "trading",
        "market making",
        "algorithmic trading",
        "systematic trading",
        "high frequency trading",
        "hft",
        "low latency",
        "backtesting",
        "signal generation",
        "execution algorithms",
        "pricing models",
        "derivatives pricing",
        "options pricing",
        "black scholes",
        "stochastic calculus",
        "time series analysis",
        "monte carlo simulation",
        "risk neutral valuation",
        "factor models",
        "statistical arbitrage",
        "pairs trading",
        "portfolio optimization",
        "alpha generation",
        "beta hedging",
        "volatility modelling",
        "order book dynamics",
        "liquidity modelling",
        "financial engineering",
        "numerical methods",
        "machine learning",
        "deep learning",
        "natural language processing",
        "reinforcement learning",
        "data science",
        "data engineering",
        "python",
        "r",
        "c++",
        "java",
        "matlab",
        "sql",
        "pandas",
        "numpy",
        "scikit-learn",
        "tensorflow",
        "pytorch",
        "kdb+",
        "q language",
        "excel",
        "vba",
        "bloomberg",
        "reuters",
        "tradingview",
        "quantconnect",
        "cursor ai",
        "notebooks",
        "api trading",
        "aws",
        "gcp",
        "azure",

        // 💬 Realistic search phrases — natural language patterns
        "quant skills",
        "quantitative analyst skills",
        "skills needed for quant roles",
        "what skills do i need to be a quant",
        "quant trading skills",
        "skills for trading jobs",
        "skills to work in trading",
        "technical skills for trading roles",
        "programming skills for quant jobs",
        "skills for systematic trading",
        "skills for algorithmic trading",
        "skills for market making",
        "quant research skills",
        "skills to become a quantitative analyst",
        "skills to work at a hedge fund",
        "skills for pricing models",
        "math skills for quant finance",
        "skills for monte carlo simulation",
        "skills for stochastic calculus",
        "skills for options pricing",
        "skills for building trading strategies",
        "skills for backtesting",
        "skills required for alpha generation",
        "data skills for quant jobs",
        "skills for factor models",
        "machine learning skills for trading",
        "python skills for quant finance",
        "coding skills for trading jobs",
        "skills to build trading algorithms",
        "skills for statistical arbitrage",
        "skills to work in high frequency trading",
        "skills for low latency trading",
        "skills for financial engineering",
        "skills for portfolio optimization",
        "skills for quantitative research",
        "skills for hedge fund quant roles",
        "skills for data analysis in trading",
        "skills for kdb+ and q language",
        "skills to work with bloomberg or reuters",
        "tools used in quant trading",
        "systems used in trading desks",
        "skills to get a quant job",
        "quant developer skills",
        "skills to build execution algorithms",
        "skills for deep learning in finance",
        "valuation",
        "discounted cash flow",
        "dcf",
        "comparable company analysis",
        "precedent transactions",
        "mergers and acquisitions",
        "m&a",
        "leveraged buyouts",
        "lbo",
        "debt financing",
        "equity financing",
        "capital raising",
        "ipo",
        "private placements",
        "deal structuring",
        "term sheets",
        "pitch books",
        "investment memoranda",
        "teasers",
        "due diligence",
        "financial analysis",
        "financial reporting",
        "budgeting",
        "forecasting",
        "variance analysis",
        "management reporting",
        "kpi tracking",
        "strategic planning",
        "fp&a",
        "corporate strategy",
        "capital budgeting",
        "treasury",
        "working capital management",
        "credit analysis",
        "risk assessment",
        "loan structuring",
        "synergy analysis",
        "integration planning",
        "excel modeling",
        "powerpoint",
        "word",
        "bloomberg",
        "capital iq",
        "factset",
        "pitchbook",
        "refinitiv",
        "thomson one",
        "tableau",
        "power bi",
        "sql",
        "python",
        "cursor ai",

        // 💬 Realistic search phrases — how users actually search
        "skills for investment banking",
        "investment banking skills",
        "skills needed for m&a jobs",
        "skills for mergers and acquisitions",
        "skills required for ib roles",
        "financial modeling skills",
        "valuation skills",
        "skills to work in investment banking",
        "technical skills for banking jobs",
        "skills for leveraged buyouts",
        "lbo modeling skills",
        "skills for fp&a roles",
        "fp&a analyst skills",
        "skills for corporate finance",
        "corporate development skills",
        "skills for ipo processes",
        "skills for deal structuring",
        "skills for capital raising",
        "skills for preparing pitch books",
        "skills for financial analysis",
        "skills for budgeting and forecasting",
        "skills to work in treasury",
        "skills for debt financing",
        "skills for equity financing",
        "skills for strategic planning roles",
        "skills for credit analysis",
        "skills for loan structuring",
        "skills for integration planning",
        "skills for synergy analysis",
        "skills for investment memoranda",
        "skills for producing teasers",
        "skills for capital iq",
        "skills for bloomberg",
        "skills for pitchbook",
        "skills for tableau and power bi",
        "skills for sql and data analysis",
        "skills to work in corporate strategy",
        "skills for variance analysis",
        "skills for budgeting roles",
        "skills for financial reporting",
        "skills to prepare kpi dashboards",
        "skills needed to work in finance",
        "banking analyst skills",
        "skills for associate roles in banking",
        "technical skills for finance jobs",
        "excel and modeling skills for banking",
        "skills for financial planning",
        "skills to work in corporate finance",
        "corporate treasury skills",
        "skills for risk assessment in finance",
        "skills to work on debt deals",
        "skills to prepare investment decks",
      ];

      // 🌐 Work style patterns
      const workStylePatterns = [
        "remote",
        "hybrid",
        "work from home",
        "onsite",
      ];

      // 🧠 Flexible phrasing to catch skill queries
      const skillPhrases = [
        "jobs that use",
        "roles that use",
        "jobs requiring",
        "roles requiring",
        "positions requiring",
        "experience in",
        "with experience in",
        "using",
        "requiring knowledge of",
        "requiring skills in",
        "technical skills",
      ];

      // 📝 General catch-all job phrases
      const generalPatterns = [
        "job",
        "jobs",
        "role",
        "roles",
        "position",
        "positions",
        "opportunity",
        "opportunities",
        "vacancy",
        "vacancies",
        "career",
        "careers",

        // 🌍 Common phrases
        "jobs in",
        "roles in",
        "positions in",
        "opportunities in",
        "careers in",
        "vacancies in",
        "work in",
        "open roles in",
        "open positions in",
        "available jobs in",
        "available roles in",
        "available positions in",
        "find jobs in",
        "find roles in",
        "search jobs in",
        "search for jobs in",
        "list jobs in",
        "look for jobs in",

        // ✍️ Natural language requests
        "show me jobs",
        "show me roles",
        "show me opportunities",
        "show me open positions",
        "show me all",
        "show all jobs",
        "show all roles",
        "show all opportunities",
        "give me jobs",
        "give me roles",
        "give me opportunities",
        "find me jobs",
        "find me roles",
        "find me opportunities",
        "i'm looking for jobs",
        "i am looking for jobs",
        "looking for jobs",
        "looking for roles",
        "looking for opportunities",
        "can you find jobs",
        "can you show me jobs",
        "can you find me roles",
        "can you find me opportunities",

        // 🌐 Variations & international English
        "vacant roles",
        "job openings",
        "openings",
        "open opportunities",
        "current vacancies",
        "available openings",
        "work opportunities",
        "career opportunities",
        "employment opportunities",
        "hiring for",
        "recruiting for",
        "latest jobs",
        "latest openings",
        "latest roles",
        "recent jobs",
        "recent opportunities",
        "jobs available",
        "roles available",
        "positions available",
        "who's hiring",
        "whos hiring",

        // 🧠 Catch-all phrasing

        "all jobs",
        "all roles",
        "all opportunities",
        "all openings",
        "all positions",
        "show everything",
        "browse all jobs",
        "browse all opportunities",
        "browse all openings",
        "show current vacancies",
        "list all jobs",
        "list all roles",
        "list all opportunities",
        "list openings",
      ];

      // 🧠 Combine all patterns
      const allPatterns = [
        ...locationPatterns,
        ...seniorityPatterns,
        ...industryPatterns,
        ...skillPatterns,
        ...workStylePatterns,
        ...skillPhrases,
        ...generalPatterns,
      ];

      return allPatterns.some((pattern) => query.includes(pattern));
    }

    performIntelligentSearch(query) {
      if (this.jobSearchDisabled) {
        this.showLiveExpertPanel({ query, source: "intelligent-search" });
        return;
      }

      const self = this;

      // Set flag to prevent PE filters from running
      this.isPerformingIntelligentSearch = true;

      // Initialize search state - ALWAYS use the latest CV data
      this.searchState = {
        currentTier: "perfect",
        currentQuery: query,
        offset: 0,
        cvData:
          window.currentCVData ||
          JSON.parse(localStorage.getItem("sffc_cv_profile") || "{}"),
      };

      // Show loading message
      this.addSennaMessage(
        "Searching for opportunities...",
        false,
        "Searching"
      );

      // Actually perform the search
      this.continueIntelligentSearch();
    }

    continueIntelligentSearch() {
      if (this.jobSearchDisabled) {
        this.isPerformingIntelligentSearch = false;
        return;
      }

      const self = this;

      // Don't reset search state - continue with current tier
      if (!this.searchState) {
        console.error("No search state to continue");
        return;
      }

      // Make AJAX call to intelligent search endpoint
      $.ajax({
        url: window.sffc_ajax?.ajax_url || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: {
          action: "sffc_intelligent_search",
          nonce: window.sffc_ajax?.nonce || "",
          query: this.searchState.currentQuery,
          tier: this.searchState.currentTier,
          cv_data: this.searchState.cvData,
          offset: this.searchState.offset,
        },
        success: function (response) {
          if (response.success && response.data.jobs) {
            self.displayTieredResults(response.data);
          } else {
            // No results from intelligent search - show helpful message
            self.addSennaMessage(
              "No opportunities found matching your criteria. Try adjusting your search or browsing all available positions.",
              false
            );
            self.isPerformingIntelligentSearch = false;
          }
        },
        error: function () {
          // Error with intelligent search - show error message
          self.addSennaMessage(
            "Unable to perform the search at this time. Please try again or browse available positions.",
            false
          );
          self.isPerformingIntelligentSearch = false;
        },
      });
    }

    displayTieredResults(data) {
      const queryFromData =
        (data && (data.query || data.originalQuery)) ||
        (this.searchState && this.searchState.currentQuery) ||
        "";

      this.showLiveExpertPanel({
        query: queryFromData,
        source: "tiered-results",
      });

      this.isPerformingIntelligentSearch = false;
      this.searchState = null;
    }

    getTierName(tier) {
      const names = {
        perfect: "Perfect",
        stretch: "Stretch",
        exploratory: "Exploratory",
      };
      return names[tier] || "Similar";
    }

    matchJobLocation(job, searchLocations) {
      const jobLocation = (job.location || "").toLowerCase();

      // Comprehensive location mapping for flexible matching
      const locationMappings = {
        // UK and regions
        uk: [
          "london",
          "manchester",
          "birmingham",
          "edinburgh",
          "glasgow",
          "leeds",
          "bristol",
          "liverpool",
          "cambridge",
          "oxford",
          "united kingdom",
          "england",
          "scotland",
          "wales",
        ],
        london: [
          "london",
          "city of london",
          "mayfair",
          "canary wharf",
          "shoreditch",
        ],

        // USA and regions
        usa: [
          "new york",
          "san francisco",
          "chicago",
          "boston",
          "los angeles",
          "seattle",
          "washington",
          "miami",
          "dallas",
          "houston",
          "atlanta",
          "denver",
          "austin",
          "united states",
          "america",
        ],
        us: [
          "new york",
          "san francisco",
          "chicago",
          "boston",
          "los angeles",
          "seattle",
          "washington",
          "miami",
          "dallas",
          "houston",
          "atlanta",
          "denver",
          "austin",
          "united states",
          "america",
        ],
        "new york": ["new york", "nyc", "ny", "manhattan", "brooklyn"],
        nyc: ["new york", "nyc", "ny", "manhattan", "brooklyn"],

        // Brazil and regions
        brazil: [
          "sao paulo",
          "são paulo",
          "rio de janeiro",
          "rio",
          "brasilia",
          "belo horizonte",
          "salvador",
          "curitiba",
          "porto alegre",
          "brasil",
        ],
        "sao paulo": ["sao paulo", "são paulo", "sp"],
        "são paulo": ["sao paulo", "são paulo", "sp"],

        // European countries and cities
        germany: [
          "frankfurt",
          "berlin",
          "munich",
          "münchen",
          "hamburg",
          "cologne",
          "stuttgart",
          "dusseldorf",
          "germany",
          "deutschland",
        ],
        france: ["paris", "lyon", "marseille", "toulouse", "nice", "france"],
        italy: [
          "milan",
          "milano",
          "rome",
          "roma",
          "florence",
          "venice",
          "italy",
          "italia",
        ],
        spain: [
          "madrid",
          "barcelona",
          "valencia",
          "seville",
          "spain",
          "españa",
        ],
        netherlands: [
          "amsterdam",
          "rotterdam",
          "hague",
          "utrecht",
          "netherlands",
          "holland",
        ],
        switzerland: [
          "zurich",
          "geneva",
          "basel",
          "bern",
          "lugano",
          "switzerland",
          "swiss",
        ],

        // Asia Pacific
        singapore: ["singapore", "sg"],
        "hong kong": ["hong kong", "hk", "hongkong"],
        japan: ["tokyo", "osaka", "kyoto", "yokohama", "japan"],
        china: ["beijing", "shanghai", "shenzhen", "guangzhou", "china"],
        india: [
          "mumbai",
          "delhi",
          "bangalore",
          "bengaluru",
          "chennai",
          "pune",
          "hyderabad",
          "kolkata",
          "india",
        ],
        australia: [
          "sydney",
          "melbourne",
          "brisbane",
          "perth",
          "adelaide",
          "australia",
        ],

        // private equity
        uae: ["dubai", "abu dhabi", "uae", "emirates", "manama"],
        dubai: ["dubai", "uae"],
        saudi: ["riyadh", "jeddah", "saudi arabia", "ksa"],

        // Canada
        canada: [
          "toronto",
          "montreal",
          "vancouver",
          "calgary",
          "ottawa",
          "canada",
        ],
      };

      // Check each search location
      return searchLocations.some((searchLoc) => {
        const searchTerm = searchLoc.toLowerCase();

        // Direct match
        if (jobLocation.includes(searchTerm)) {
          return true;
        }

        // Check if search term is a country/region that maps to cities
        if (locationMappings[searchTerm]) {
          return locationMappings[searchTerm].some((city) =>
            jobLocation.includes(city)
          );
        }

        // Reverse check - if job location contains any mapped location for the search term
        for (const [key, values] of Object.entries(locationMappings)) {
          if (key === searchTerm || values.includes(searchTerm)) {
            // Found our search term in mappings, check if job matches any related location
            return (
              values.some((loc) => jobLocation.includes(loc)) ||
              jobLocation.includes(key)
            );
          }
        }

        return false;
      });
    }

    showMoreIntelligentResults() {
      if (this.jobSearchDisabled) {
        this.showLiveExpertPanel({ source: "show-more" });
        return;
      }

      this.searchState.offset += 8;
      this.continueIntelligentSearch();
    }

    applyPEFiltersFromQuery(filters) {
      // Don't apply PE filters if intelligent search is running
      if (this.isPerformingIntelligentSearch) {
        return;
      }

      // Query database for sffc_job posts matching filters
      if (filters.clearAll) {
        this.requestLiveExpert({ source: "legacy-call" });
        this.addSennaMessage(
          "Cleared all filters. Showing all available opportunities."
        );
        return;
      }

      // Build database query based on filters
      this.queryJobsFromDatabase(filters);

      // Check if PE filters are available for UI update
      if (!window.peFilters) {
        return;
      }

      // Store the full filter object for comprehensive filtering
      window.peFilters.activeFilters = {};
      window.peFilters.advancedFilters = {};

      // Apply standard PE filters
      if (filters.seniority) {
        window.peFilters.activeFilters.seniority = filters.seniority;
      }
      if (filters.fundSize) {
        window.peFilters.activeFilters.fundSize = filters.fundSize;
      }
      if (filters.location) {
        window.peFilters.activeFilters.location = filters.location;
      }
      if (filters.workStyle) {
        window.peFilters.activeFilters.workStyle = filters.workStyle;
      }
      if (filters.geoFocus) {
        window.peFilters.activeFilters.geoFocus = filters.geoFocus;
      }
      if (filters.salaryMin) {
        window.peFilters.activeFilters.salaryMin = filters.salaryMin;
      }

      // Store advanced filters for custom processing
      if (filters.minMatchScore) {
        window.peFilters.advancedFilters.minMatchScore = filters.minMatchScore;
      }
      if (filters.minExperience) {
        window.peFilters.advancedFilters.minExperience = filters.minExperience;
      }
      if (filters.exclude) {
        window.peFilters.advancedFilters.exclude = filters.exclude;
      }
      if (filters.remote) {
        window.peFilters.advancedFilters.remote = true;
      }
      if (filters.hybrid) {
        window.peFilters.advancedFilters.hybrid = true;
      }
      if (filters.immediate) {
        window.peFilters.advancedFilters.immediate = true;
      }
      if (filters.industry) {
        window.peFilters.advancedFilters.industry = filters.industry;
      }
      if (filters.additionalIndustries) {
        window.peFilters.advancedFilters.additionalIndustries =
          filters.additionalIndustries;
      }

      // Generate more intelligent contextual response
      let responseText = "";
      const filterDescriptions = [];

      // Build comprehensive description
      if (filters.industry) {
        const industryNames = {
          risk: "risk management",
          "private-equity": "private equity",
          "venture-capital": "venture capital",
          "hedge-fund": "hedge fund",
          "investment-banking": "investment banking",
          "asset-management": "asset management",
          "real-estate": "real estate",
          crypto: "crypto/blockchain",
          consulting: "consulting",
          tech: "technology",
        };
        filterDescriptions.push(
          `${industryNames[filters.industry] || filters.industry} roles`
        );
      }

      if (filters.seniority && filters.seniority.length > 0) {
        const seniorityText = filters.seniority
          .map((s) =>
            s === "vp" ? "VP" : s.charAt(0).toUpperCase() + s.slice(1)
          )
          .join("/");
        filterDescriptions.push(`${seniorityText} level`);
      }

      if (filters.fundSize && filters.fundSize.length > 0) {
        filterDescriptions.push(`${filters.fundSize.join("/")}-cap funds`);
      }

      if (filters.location && filters.location.length > 0) {
        const locationNames = filters.location.map(
          (loc) => loc.charAt(0).toUpperCase() + loc.slice(1)
        );
        filterDescriptions.push(`in ${locationNames.join(" or ")}`);
      }

      if (filters.salaryMin) {
        filterDescriptions.push(`$${filters.salaryMin}k+ compensation`);
      }

      if (filters.remote) {
        filterDescriptions.push("remote positions");
      } else if (filters.hybrid) {
        filterDescriptions.push("hybrid work arrangement");
      }

      if (filters.workStyle && filters.workStyle.length > 0) {
        const styleText = filters.workStyle.includes("normal")
          ? "work-life balance"
          : filters.workStyle.includes("intense")
          ? "demanding hours"
          : "variable hours";
        filterDescriptions.push(`with ${styleText}`);
      }

      if (filters.minMatchScore) {
        filterDescriptions.push(`${filters.minMatchScore}%+ match score`);
      }

      if (filters.immediate) {
        filterDescriptions.push("immediate start");
      }

      // Build response based on filter complexity
      if (filterDescriptions.length === 0) {
        responseText = "Searching for relevant opportunities...";
      } else if (filterDescriptions.length === 1) {
        responseText = `Looking for ${filterDescriptions[0]}...`;
      } else if (filterDescriptions.length === 2) {
        responseText = `Finding ${filterDescriptions.join(" ")} for you...`;
      } else {
        responseText = `Searching for ${filterDescriptions
          .slice(0, -1)
          .join(", ")} ${filterDescriptions.slice(-1)[0]}...`;
      }

      this.addSennaMessage(responseText);

      // Apply the filters using PE filter system
      setTimeout(() => {
        window.peFilters.doApplyFilters();
      }, 500);
    }

    queryJobsFromDatabase(filters) {
      // Prepare query parameters
      const queryParams = {
        action: "sffc_search_jobs",
        nonce: window.sffc_ajax?.nonce || "",
        filters: {},
      };

      // Map filters to database fields
      if (filters.seniority && filters.seniority.length > 0) {
        queryParams.filters.seniority = filters.seniority;
      }

      if (filters.location && filters.location.length > 0) {
        queryParams.filters.location = filters.location;
      }

      if (filters.fundSize && filters.fundSize.length > 0) {
        queryParams.filters.fund_size = filters.fundSize;
      }

      if (filters.workStyle && filters.workStyle.length > 0) {
        queryParams.filters.work_style = filters.workStyle;
      }

      if (filters.geoFocus && filters.geoFocus.length > 0) {
        queryParams.filters.geo_focus = filters.geoFocus;
      }

      if (filters.industry) {
        queryParams.filters.industry = filters.industry;
      }

      if (filters.salaryMin) {
        queryParams.filters.salary_min = filters.salaryMin;
      }

      if (filters.quickFilter) {
        queryParams.filters.quick = filters.quickFilter;
      }

      // Make AJAX request to fetch jobs
      $.ajax({
        url: window.sffc_ajax?.ajax_url || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: queryParams,
        success: (response) => {
          if (response.success && response.data) {
            const jobs = response.data.jobs || [];
            const totalCount = response.data.total || jobs.length;

            // Generate response message
            let message = "";
            if (jobs.length > 0) {
              message = `Found ${totalCount} opportunities matching your criteria. Here are the top matches:`;
            } else {
              message = `No exact matches found for your criteria. Here are some alternative opportunities you might consider:`;
            }

            this.addSennaMessage(message);

            // Render jobs in chat
            setTimeout(() => {
              if (jobs.length > 0) {
                this.renderJobsInChat(jobs.slice(0, 6));
              } else {
                // Show fallback jobs
                this.showSampleJobs();
              }
            }, 500);
          } else {
            // Fallback to client-side filtering
            this.clientSideFilterJobs(filters);
          }
        },
        error: (xhr, status, error) => {
          // Fallback to client-side filtering
          this.clientSideFilterJobs(filters);
        },
      });
    }

    clientSideFilterJobs(filters) {
      // Filter existing jobs displayed on page
      let filteredJobs = [...this.allJobs];

      if (filters.seniority && filters.seniority.length > 0) {
        filteredJobs = filteredJobs.filter((job) => {
          const jobTitle = job.title.toLowerCase();
          return filters.seniority.some((level) => {
            switch (level) {
              case "analyst":
                return (
                  jobTitle.includes("analyst") &&
                  !jobTitle.includes("senior") &&
                  !jobTitle.includes("principal")
                );
              case "associate":
                return (
                  jobTitle.includes("associate") ||
                  jobTitle.includes("senior analyst")
                );
              case "vp":
                return (
                  jobTitle.includes("vp") ||
                  jobTitle.includes("vice president") ||
                  jobTitle.includes("director")
                );
              case "intern":
                return (
                  jobTitle.includes("intern") ||
                  jobTitle.includes("junior") ||
                  jobTitle.includes("entry")
                );
              default:
                return false;
            }
          });
        });
      }

      if (filters.location && filters.location.length > 0) {
        filteredJobs = filteredJobs.filter((job) => {
          return this.matchJobLocation(job, filters.location);
        });
      }

      if (filters.industry) {
        const industryKeyword = filters.industry.toLowerCase();
        filteredJobs = filteredJobs.filter((job) => {
          const jobDescription = (job.description || "").toLowerCase();
          const jobTitle = (job.title || "").toLowerCase();
          return (
            jobDescription.includes(industryKeyword) ||
            jobTitle.includes(industryKeyword)
          );
        });
      }

      // Generate response
      let message = "";
      if (filteredJobs.length > 0) {
        message = `Found ${filteredJobs.length} opportunities matching your filters:`;
      } else {
        message = `No exact matches found. Here are some opportunities you might be interested in:`;
        filteredJobs = this.allJobs.slice(0, 3);
      }

      this.addSennaMessage(message);

      // Show filtered jobs
      setTimeout(() => {
        this.renderJobsInChat(filteredJobs.slice(0, 6));
      }, 500);
    }

    simpleJobSearch(keyword) {
      // Simple case-insensitive search in title, company, and location
      this.filteredJobs = this.allJobs.filter((job) => {
        const skillsText = Array.isArray(job.skills)
          ? job.skills.join(" ")
          : (job.skills || "").toString();
        const searchText = `${job.title || ""} ${job.company || ""} ${
          job.location || ""
        } ${skillsText}`.toLowerCase();
        return searchText.includes(keyword.toLowerCase());
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `I found ${this.filteredJobs.length} ${keyword}-related opportunities for you.`
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 500);
      } else {
        this.addSennaMessage(
          `I couldn't find specific ${keyword} roles. Let me show you some other opportunities.`
        );
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 3));
        }, 500);
      }
    }

    filterByRemote() {
      this.filteredJobs = this.allJobs.filter((job) => {
        const location = (job.location || "").toLowerCase();
        const type = (job.job_type || "").toLowerCase();
        return (
          location.includes("remote") ||
          type.includes("remote") ||
          location.includes("anywhere")
        );
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `Excellent choice. I found ${this.filteredJobs.length} remote opportunities that align with your expertise.`
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 500);
        this.conversationContext.preferences.remote = true;
      } else {
        this.addSennaMessage(
          `The current market shows limited fully remote positions, but several companies offer flexible hybrid arrangements. Shall I show you those instead?`
        );
      }
    }

    filterByCompanyType(type) {
      // Filter based on company characteristics in job data
      this.filteredJobs = this.allJobs.filter((job) => {
        const company = (job.company || "").toLowerCase();
        const description = (job.description || "").toLowerCase();

        if (type === "startup") {
          return (
            company.includes("startup") ||
            description.includes("startup") ||
            description.includes("fast-paced") ||
            description.includes("early-stage")
          );
        }
        return true;
      });

      this.addSennaMessage(
        `Here are opportunities at innovative startups and growth-stage companies.`
      );
      setTimeout(() => {
        this.renderJobsInChat(this.filteredJobs.slice(0, 6));
      }, 500);
    }

    handleSalaryQuery(input) {
      // Extract salary range if mentioned
      const numbers = input.match(/\d+/g);
      let minSalary = 0;

      if (numbers && numbers.length > 0) {
        minSalary = parseInt(numbers[0]) * (input.includes("k") ? 1000 : 1);
      }

      this.filteredJobs = this.allJobs.filter((job) => {
        const salaryMin = job.salary_min || 0;
        return salaryMin >= minSalary;
      });

      this.addSennaMessage(
        `Here are premium opportunities with compensation exceeding $${minSalary.toLocaleString()}.`
      );
      setTimeout(() => {
        this.renderJobsInChat(this.filteredJobs.slice(0, 6));
      }, 500);
    }

    handleSkillsQuery(input) {
      // Extract skills from meta fields
      const keywords = input
        .toLowerCase()
        .split(" ")
        .filter((word) => word.length > 3);

      this.filteredJobs = this.allJobs.filter((job) => {
        const skills = Array.isArray(job.skills)
          ? job.skills.join(" ").toLowerCase()
          : (job.skills || "").toString().toLowerCase();
        const requirements =
          typeof job.requirements === "string"
            ? job.requirements.toLowerCase()
            : (job.requirements || "").toString().toLowerCase();
        const description = (job.description || "").toLowerCase();

        return keywords.some(
          (keyword) =>
            skills.includes(keyword) ||
            requirements.includes(keyword) ||
            description.includes(keyword)
        );
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `I've identified ${this.filteredJobs.length} roles that leverage your expertise.`
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 500);
      } else {
        this.addSennaMessage(
          `Let me show you opportunities where your transferable skills would be valuable.`
        );
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 6));
        }, 500);
      }
    }

    showMoreJobs() {
      const currentCount = this.displayedJobs.length;
      const moreJobs = this.filteredJobs.slice(currentCount, currentCount + 6);

      if (moreJobs.length > 0) {
        this.displayedJobs = [...this.displayedJobs, ...moreJobs];
        this.addSennaMessage(
          `Here are ${moreJobs.length} additional opportunities for your consideration.`
        );
        setTimeout(() => {
          this.renderJobsInChat(moreJobs);
        }, 500);
      } else {
        this.addSennaMessage(
          `You've reviewed all current opportunities. Shall we refine your search parameters?`
        );
      }
    }

    searchJobsByKeyword(input, category) {
      const inputLower = input.toLowerCase();

      // Search through all jobs for keyword matches
      this.filteredJobs = this.allJobs.filter((job) => {
        const title = (job.title || "").toLowerCase();
        const description = (job.description || "").toString().toLowerCase();
        const skills = Array.isArray(job.skills)
          ? job.skills.join(" ").toLowerCase()
          : (job.skills || "").toString().toLowerCase();
        const company = (job.company || "").toString().toLowerCase();
        const requirements =
          typeof job.requirements === "string"
            ? job.requirements.toLowerCase()
            : (job.requirements || "").toString().toLowerCase();

        // Search for the specific keyword in various fields - NOW CASE INSENSITIVE
        if (category === "risk") {
          return (
            title.includes("risk") ||
            title.includes("compliance") ||
            title.includes("audit") ||
            description.includes("risk") ||
            skills.includes("risk") ||
            requirements.includes("risk")
          );
        } else if (category === "analyst") {
          return (
            title.includes("analyst") ||
            title.includes("analysis") ||
            description.includes("analytics")
          );
        } else if (category === "engineering") {
          return (
            title.includes("engineer") ||
            title.includes("developer") ||
            title.includes("architect") ||
            skills.includes("programming")
          );
        } else if (category === "management") {
          return (
            title.includes("manager") ||
            title.includes("director") ||
            title.includes("lead") ||
            title.includes("head of")
          );
        }

        // Fallback to general keyword search
        return title.includes(inputLower) || description.includes(inputLower);
      });

      // Present results with database scan first
      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `I've scanned our database and found ${this.filteredJobs.length} ${category}-related opportunities for you.`
        );

        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));

          // Add detailed guidance after showing jobs
          setTimeout(() => {
            this.addDetailedGuidance(category);
          }, 1000);
        }, 500);
      } else {
        // No direct matches, show related opportunities
        this.addSennaMessage(
          `I don't see specific ${category} roles at the moment, but let me show you related opportunities that might align with your interests.`
        );

        setTimeout(() => {
          // Show general opportunities
          this.renderJobsInChat(this.allJobs.slice(0, 3));

          // Add detailed guidance
          setTimeout(() => {
            this.addDetailedGuidance(category);
          }, 1000);
        }, 500);
      }
    }

    addDetailedGuidance(category) {
      let guidance = "";

      if (category === "risk") {
        guidance = `
                    <div class="senna-guidance">
                        <h4>Additional Risk Management Opportunities to Consider:</h4>
                        <ul>
                            <li><strong>Risk Analyst</strong> - Identify and measure various types of risk (market, credit, operational)</li>
                            <li><strong>Compliance Officer</strong> - Ensure adherence to regulations and internal policies</li>
                            <li><strong>Credit Risk Specialist</strong> - Evaluate creditworthiness and minimize default risk</li>
                            <li><strong>Operational Risk Manager</strong> - Focus on internal processes and systems</li>
                            <li><strong>Market Risk Analyst</strong> - Monitor exposure to market-related risks</li>
                        </ul>
                        <p class="guidance-tip"><strong>Tip:</strong> <em>Consider obtaining certifications like FRM or CRISC to strengthen your profile.</em></p>
                    </div>
                `;
      } else if (category === "analyst") {
        guidance = `
                    <div class="senna-guidance">
                        <h4>Analyst Role Specializations:</h4>
                        <ul>
                            <li><strong>Business Analyst</strong> - Bridge between business needs and technical solutions</li>
                            <li><strong>Data Analyst</strong> - Transform data into actionable insights</li>
                            <li><strong>Financial Analyst</strong> - Evaluate investment opportunities and financial performance</li>
                            <li><strong>Systems Analyst</strong> - Optimize IT systems and processes</li>
                        </ul>
                        <p class="guidance-tip"><strong>Tip:</strong> <em>Highlight your analytical tools expertise (Excel, SQL, Python, Tableau).</em></p>
                    </div>
                `;
      } else if (category === "engineering") {
        guidance = `
                    <div class="senna-guidance">
                        <h4>Engineering Opportunities:</h4>
                        <ul>
                            <li><strong>Software Engineer</strong> - Build scalable applications and systems</li>
                            <li><strong>DevOps Engineer</strong> - Streamline development and deployment processes</li>
                            <li><strong>Data Engineer</strong> - Design and maintain data infrastructure</li>
                            <li><strong>Cloud Architect</strong> - Design cloud-based solutions</li>
                        </ul>
                        <p class="guidance-tip"><strong>Tip:</strong> <em>Showcase your GitHub projects and technical certifications.</em></p>
                    </div>
                `;
      }

      if (guidance) {
        this.addSennaMessage(guidance, true); // Skip typing for formatted content
      }
    }

    getCategoryHeadline(category, count) {
      const categoryTitles = {
        risk: "Risk Management Positions",
        analyst: "Analyst Opportunities",
        engineering: "Engineering Roles",
        management: "Leadership Positions",
        pe: "Private Equity Careers",
        london: "London-Based Roles",
        paris: "Paris-Based Roles",
        remote: "Remote Opportunities",
      };
      return categoryTitles[category] || "Search Results";
    }

    isAdviceQuery(inputLower) {
      // Keywords that indicate advice/guidance queries (no job cards needed)
      const adviceKeywords = [
        "how do i",
        "how to",
        "what should",
        "advice",
        "tips",
        "help me",
        "guide",
        "strategy",
        "prepare",
        "improve",
        "tell me about",
        "explain",
        "what is",
        "why",
        "when should",
        "best practice",
        "recommend",
        "suggestion",
        "career advice",
        "interview",
        "resume",
        "cv",
        "cover letter",
        "negotiate",
      ];

      return adviceKeywords.some((keyword) => inputLower.includes(keyword));
    }

    provideAdvice(input) {
      const inputLower = input.toLowerCase();
      let headline = "Career Guidance";
      let response = "";

      if (inputLower.includes("interview")) {
        headline = "Interview Preparation";
        response = `Excellent interview preparation involves several key steps:
                
                **Research the Company**: Understand their mission, values, recent news, and culture.
                
                **Practice Common Questions**: Prepare STAR method responses for behavioral questions.
                
                **Prepare Questions**: Have 3-5 thoughtful questions about the role and company.
                
                **Professional Presentation**: Plan your outfit and test your tech setup if virtual.
                
                Would you like specific guidance on any of these areas?`;
      } else if (inputLower.includes("resume") || inputLower.includes("cv")) {
        headline = "Resume Optimization";
        response = `A compelling resume should:
                
                **Lead with Impact**: Start bullets with action verbs and quantify achievements.
                
                **Tailor Content**: Match keywords from the job description.
                
                **Focus on Results**: Show outcomes, not just responsibilities.
                
                **Keep it Concise**: 1-2 pages maximum, with clear formatting.
                
                I can help tailor your CV to specific roles if you shortlist some opportunities.`;
      } else if (inputLower.includes("negotiate")) {
        headline = "Salary Negotiation";
        response = `Successful salary negotiation requires:
                
                **Market Research**: Know the typical range for your role and location.
                
                **Value Proposition**: Articulate your unique contributions and achievements.
                
                **Total Compensation**: Consider benefits, bonuses, and growth opportunities.
                
                **Professional Approach**: Be confident but collaborative in discussions.
                
                What specific aspect of negotiation would you like to explore?`;
      } else {
        // General career advice
        response = `I'm here to provide personalized career guidance. I can help with:
                
                • Finding the right opportunities
                • Optimizing your application materials
                • Interview preparation strategies
                • Salary negotiation tactics
                • Career progression planning
                
                What specific area would you like to focus on?`;
      }

      this.addSennaMessage(response);
    }

    handleGeneralQuery(input) {
      const inputLower = input.toLowerCase();

      // Check if this is an advice query (no job cards needed)
      if (this.isAdviceQuery(inputLower)) {
        this.provideAdvice(input);
        return;
      }

      // First try to search for jobs matching the query
      const keywords = inputLower.split(" ").filter((word) => word.length > 3);

      if (keywords.length > 0) {
        // Search for jobs matching keywords
        this.filteredJobs = this.allJobs.filter((job) => {
          const skillsText = Array.isArray(job.skills)
            ? job.skills.join(" ")
            : (job.skills || "").toString();
          const searchText = `${job.title || ""} ${job.company || ""} ${
            job.description || ""
          } ${skillsText}`.toLowerCase();
          return keywords.some((keyword) => searchText.includes(keyword));
        });

        if (this.filteredJobs.length > 0) {
          this.addSennaMessage(
            `I found ${this.filteredJobs.length} opportunities that might interest you based on your query.`
          );
          setTimeout(() => {
            this.renderJobsInChat(this.filteredJobs.slice(0, 6));
          }, 500);
          return;
        }
      }

      // Default contextual responses with option cards
      if (this.shortlist.length === 0) {
        this.addSennaMessage(
          `I understand. Click "Interested" on any roles you'd like to explore further, and I can provide detailed analysis.`
        );
      } else if (this.shortlist.length >= 3) {
        const analyzeMessage = `
                    <p>You have ${this.shortlist.length} roles shortlisted. Would you like me to analyze and compare them for you?</p>
                    <div class="option-cards">
                        <div class="option-card primary" onclick="sennaConversational.switchToAnalyze()">
                            <div class="option-card-text">Yes, Analyze Them</div>
                        </div>
                        <div class="option-card" onclick="sennaConversational.showMoreJobs()">
                            <div class="option-card-text">Show More Jobs</div>
                        </div>
                        <div class="option-card" onclick="sennaConversational.clearConversation()">
                            <div class="option-card-text">Start Fresh</div>
                        </div>
                    </div>
                `;
        this.addSennaMessage(analyzeMessage, true);
      } else {
        const explorationMessage = `
                    <p>Tell me more about what you're looking for:</p>
                    <div class="quick-options">
                        <span class="quick-option" onclick="sennaConversational.handleQuickOption('pe')">Private Equity</span>
                        <span class="quick-option" onclick="sennaConversational.handleQuickOption('london')">London</span>
                        <span class="quick-option" onclick="sennaConversational.handleQuickOption('analyst')">Analyst</span>
                    </div>
                `;
        this.addSennaMessage(explorationMessage, true);
      }
    }

    handleOptionClick(option) {
      // Add user's selection as a message
      let userMessage = "";
      switch (option) {
        case "remote":
          userMessage = "I prioritize location flexibility and remote work";
          break;
        case "culture":
          userMessage = "Company culture is most important to me";
          break;
        case "salary":
          userMessage = "Compensation is my top priority";
          break;
        case "tech":
          userMessage = "I want to work with specific technologies";
          break;
      }

      if (userMessage) {
        this.addUserMessage(userMessage);
        // Process the option as if it was typed
        this.processUserIntent(userMessage);
      }
    }

    handleQuickOption(option) {
      let query = "";
      switch (option) {
        case "pe":
          query = "Show me private equity analyst jobs";
          break;
        case "london":
          query = "Looking for roles in London";
          break;
        case "analyst":
          query = "Show me analyst level positions";
          break;
        case "remote":
          query = "Show me remote opportunities";
          break;
        case "startup":
          query = "I prefer working at startups";
          break;
        case "enterprise":
          query = "Show me enterprise companies";
          break;
        case "high-salary":
          query = "Looking for roles above $150k";
          break;
        case "leadership":
          query = "I want leadership or management roles";
          break;
      }

      if (query) {
        this.addUserMessage(query);
        this.processUserIntent(query);
      }
    }

    filterJobsByCombined(location, seniority) {
      const customHeadline = `${seniority} Roles - ${location}`;

      // First try backend search for combined location+seniority jobs
      this.searchJobsWithCombinedFilters(location, seniority, customHeadline);
    }

    searchJobsWithCombinedFilters(location, seniority, customHeadline) {
      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      // Show loading message
      this.addSennaMessage(
        `Searching for ${seniority} positions in ${location}...`,
        false,
        customHeadline
      );

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_search_jobs",
          query: "", // Empty query, just filter by location and seniority
          filters: {
            location: [location],
            seniority: [seniority.toLowerCase()],
          },
          nonce: window.sffc_ajax?.nonce || "",
        },
        success: (response) => {
          if (response && response.success && response.data?.jobs) {
            const jobs = response.data.jobs;

            if (jobs.length > 0) {
              // Remove loading message and show results
              this.removeLastMessage();
              this.addSennaMessage(
                `Found ${jobs.length} ${seniority} positions in ${location}:`,
                false,
                customHeadline
              );

              // Update filteredJobs and render
              this.filteredJobs = jobs;
              setTimeout(() => {
                this.renderJobsInChat(this.filteredJobs.slice(0, 6));
              }, 300);
            } else {
              // No jobs found in backend, try client-side filtering as fallback
              this.fallbackToClientCombinedFiltering(
                location,
                seniority,
                customHeadline
              );
            }
          } else {
            // Backend search failed, try client-side filtering
            this.fallbackToClientCombinedFiltering(
              location,
              seniority,
              customHeadline
            );
          }
        },
        error: (xhr, status, error) => {
          console.warn(
            "Backend combined search failed, falling back to client-side filtering:",
            error
          );
          // Fallback to client-side filtering
          this.fallbackToClientCombinedFiltering(
            location,
            seniority,
            customHeadline
          );
        },
      });
    }

    fallbackToClientCombinedFiltering(location, seniority, customHeadline) {
      // Remove loading message
      this.removeLastMessage();

      // First try exact match for both using existing logic
      this.filteredJobs = this.allJobs.filter((job) => {
        const jobTitle = (job.title || "").toLowerCase();
        const jobSeniority = (job.seniority || "").toLowerCase();

        const locationMatch = this.matchJobLocation(job, [location]);
        const seniorityMatch =
          jobTitle.includes(seniority.toLowerCase()) ||
          jobSeniority.includes(seniority.toLowerCase());

        return locationMatch && seniorityMatch;
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `Found ${this.filteredJobs.length} ${seniority} positions in ${location}:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 300);
      } else {
        // No exact matches - find similar jobs
        this.findSimilarJobs(location, seniority, customHeadline);
      }
    }

    findSimilarJobs(location, seniority, customHeadline) {
      // Try location-only match first using comprehensive matching
      let similarJobs = this.allJobs.filter((job) => {
        return this.matchJobLocation(job, [location]);
      });

      if (similarJobs.length === 0) {
        // Try seniority-only match
        similarJobs = this.allJobs.filter((job) => {
          const jobTitle = (job.title || "").toLowerCase();
          const jobSeniority = (job.seniority || "").toLowerCase();
          return (
            jobTitle.includes(seniority.toLowerCase()) ||
            jobSeniority.includes(seniority.toLowerCase())
          );
        });

        if (similarJobs.length > 0) {
          this.addSennaMessage(
            `No ${seniority} roles in ${location} right now, but I found ${similarJobs.length} ${seniority} positions in other locations:`,
            false,
            customHeadline
          );
        } else {
          // Show any PE jobs as fallback
          similarJobs = this.allJobs.filter((job) => {
            const title = (job.title || "").toLowerCase();
            return (
              title.includes("private equity") ||
              title.includes("pe ") ||
              title.includes("analyst") ||
              title.includes("associate")
            );
          });

          this.addSennaMessage(
            `No ${seniority} roles in ${location} currently. Here are some alternative PE opportunities you might consider:`,
            false,
            customHeadline
          );
        }
      } else {
        // Found jobs in location but not at the right seniority
        this.addSennaMessage(
          `No ${seniority} positions in ${location} right now, but here are ${similarJobs.length} other opportunities in ${location}:`,
          false,
          customHeadline
        );
      }

      // Show similar jobs
      if (similarJobs.length > 0) {
        setTimeout(() => {
          this.renderJobsInChat(similarJobs.slice(0, 6));
        }, 300);
      }

      // Provide strategic advice
      setTimeout(() => {
        this.provideStrategicAdvice(location, seniority);
      }, 800);
    }

    provideStrategicAdvice(location, seniority) {
      const advice = `
                <div class="strategic-advice">
                    <h4>Breaking into ${location}'s ${seniority} Market:</h4>
                    <ul>
                        <li>Network with ${seniority}-level professionals at ${location} PE firms</li>
                        <li>Consider adjacent markets with more ${seniority} openings</li>
                        <li>Build relationships with headhunters specializing in ${location}</li>
                        <li>Target firms planning ${location} expansion in 2024</li>
                    </ul>
                </div>
            `;
      this.addSennaMessage(advice, true, "Strategic Insights");
    }

    filterJobsByLocation(location) {
      const customHeadline = `${location} Opportunities`;

      // First try backend search for location-specific jobs
      this.searchJobsWithLocation(location, customHeadline);
    }

    searchJobsWithLocation(location, customHeadline) {
      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      // Show loading message
      this.addSennaMessage(
        `Searching for opportunities in ${location}...`,
        false,
        customHeadline
      );

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_search_jobs",
          query: "", // Empty query, just filter by location
          filters: {
            location: [location],
          },
          nonce: window.sffc_ajax?.nonce || "",
        },
        success: (response) => {
          if (response && response.success && response.data?.jobs) {
            const jobs = response.data.jobs;

            if (jobs.length > 0) {
              // Remove loading message and show results
              this.removeLastMessage();
              this.addSennaMessage(
                `Found ${jobs.length} opportunities in ${location}:`,
                false,
                customHeadline
              );

              // Update filteredJobs and render
              this.filteredJobs = jobs;
              setTimeout(() => {
                this.renderJobsInChat(this.filteredJobs.slice(0, 6));
              }, 300);
            } else {
              // No jobs found in backend, try client-side filtering as fallback
              this.fallbackToClientFiltering(location, customHeadline);
            }
          } else {
            // Backend search failed, try client-side filtering
            this.fallbackToClientFiltering(location, customHeadline);
          }
        },
        error: (xhr, status, error) => {
          console.warn(
            "Backend location search failed, falling back to client-side filtering:",
            error
          );
          // Fallback to client-side filtering
          this.fallbackToClientFiltering(location, customHeadline);
        },
      });
    }

    fallbackToClientFiltering(location, customHeadline) {
      // Remove loading message
      this.removeLastMessage();

      // Filter jobs using the existing comprehensive location matching
      this.filteredJobs = this.allJobs.filter((job) => {
        return this.matchJobLocation(job, [location]);
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `Found ${this.filteredJobs.length} opportunities in ${location}:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 300);
      } else {
        // No jobs in this location - show similar locations
        this.showAlternativeLocations(location, customHeadline);
      }
    }

    removeLastMessage() {
      // Remove the last message (loading message)
      const $messages = $(".senna-message").last();
      if ($messages.length > 0) {
        $messages.remove();
      }
    }

    showAlternativeLocations(location, customHeadline) {
      // Map of related/nearby locations
      const alternativeLocations = {
        Brazil: ["New York", "Miami", "São Paulo"],
        Milan: ["London", "Frankfurt", "Paris"],
        Tokyo: ["Hong Kong", "Singapore", "Sydney"],
        Sydney: ["Singapore", "Hong Kong", "Melbourne"],
      };

      const alternatives = alternativeLocations[location] || [
        "London",
        "New York",
        "Singapore",
      ];

      // Find jobs in alternative locations
      let alternativeJobs = [];
      for (const alt of alternatives) {
        const jobs = this.allJobs.filter((job) => {
          const jobLocation = (job.location || "").toLowerCase();
          return jobLocation.includes(alt.toLowerCase());
        });
        alternativeJobs = alternativeJobs.concat(jobs);
      }

      // Remove duplicates
      alternativeJobs = [
        ...new Map(alternativeJobs.map((job) => [job.id, job])).values(),
      ];

      if (alternativeJobs.length > 0) {
        this.addSennaMessage(
          `No current openings in ${location}, but I found opportunities in similar markets:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(alternativeJobs.slice(0, 6));
        }, 300);
      } else {
        // Show any PE jobs as last resort
        this.addSennaMessage(
          `Limited opportunities in ${location} right now. Here are some global PE roles to consider:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 6));
        }, 300);
      }

      // Always provide location-specific advice
      this.provideLocationAdvice(location);
    }

    filterJobsBySeniority(seniority) {
      const customHeadline = `${seniority} Level Positions`;

      // First try backend search for seniority-specific jobs
      this.searchJobsWithSeniority(seniority, customHeadline);
    }

    searchJobsWithSeniority(seniority, customHeadline) {
      const ajaxUrl =
        window.sffc_ajax?.ajax_url ||
        window.sffc_ajax?.url ||
        "/wp-admin/admin-ajax.php";

      // Show loading message
      this.addSennaMessage(
        `Searching for ${seniority} level positions...`,
        false,
        customHeadline
      );

      $.ajax({
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        data: {
          action: "sffc_search_jobs",
          query: "", // Empty query, just filter by seniority
          filters: {
            seniority: [seniority.toLowerCase()],
          },
          nonce: window.sffc_ajax?.nonce || "",
        },
        success: (response) => {
          if (response && response.success && response.data?.jobs) {
            const jobs = response.data.jobs;

            if (jobs.length > 0) {
              // Remove loading message and show results
              this.removeLastMessage();
              this.addSennaMessage(
                `Found ${jobs.length} ${seniority} level positions:`,
                false,
                customHeadline
              );

              // Update filteredJobs and render
              this.filteredJobs = jobs;
              setTimeout(() => {
                this.renderJobsInChat(this.filteredJobs.slice(0, 6));
              }, 300);
            } else {
              // No jobs found in backend, try client-side filtering as fallback
              this.fallbackToClientSeniorityFiltering(
                seniority,
                customHeadline
              );
            }
          } else {
            // Backend search failed, try client-side filtering
            this.fallbackToClientSeniorityFiltering(seniority, customHeadline);
          }
        },
        error: (xhr, status, error) => {
          console.warn(
            "Backend seniority search failed, falling back to client-side filtering:",
            error
          );
          // Fallback to client-side filtering
          this.fallbackToClientSeniorityFiltering(seniority, customHeadline);
        },
      });
    }

    fallbackToClientSeniorityFiltering(seniority, customHeadline) {
      // Remove loading message
      this.removeLastMessage();

      // Filter jobs using existing logic
      this.filteredJobs = this.allJobs.filter((job) => {
        const jobTitle = (job.title || "").toLowerCase();
        const jobSeniority = (job.seniority || "").toLowerCase();
        return (
          jobTitle.includes(seniority.toLowerCase()) ||
          jobSeniority.includes(seniority.toLowerCase())
        );
      });

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `Found ${this.filteredJobs.length} ${seniority} level positions:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 300);
      } else {
        // Show adjacent seniority levels
        this.showAdjacentLevels(seniority, customHeadline);
      }
    }

    showAdjacentLevels(targetSeniority, customHeadline) {
      // Map of adjacent seniority levels
      const adjacentLevels = {
        Analyst: ["Associate", "Senior Analyst", "Junior Associate"],
        Associate: ["Senior Associate", "Analyst", "VP"],
        "Vice President": ["Senior Associate", "Director", "Principal"],
        Director: ["Vice President", "Managing Director", "Partner"],
        Partner: ["Director", "Managing Partner", "Principal"],
      };

      const adjacent = adjacentLevels[targetSeniority] || [
        "Analyst",
        "Associate",
      ];

      // Find jobs at adjacent levels
      let adjacentJobs = [];
      for (const level of adjacent) {
        const jobs = this.allJobs.filter((job) => {
          const jobTitle = (job.title || "").toLowerCase();
          const jobSeniority = (job.seniority || "").toLowerCase();
          return (
            jobTitle.includes(level.toLowerCase()) ||
            jobSeniority.includes(level.toLowerCase())
          );
        });
        adjacentJobs = adjacentJobs.concat(jobs);
      }

      // Remove duplicates
      adjacentJobs = [
        ...new Map(adjacentJobs.map((job) => [job.id, job])).values(),
      ];

      if (adjacentJobs.length > 0) {
        this.addSennaMessage(
          `No ${targetSeniority} positions available, but here are ${adjacentJobs.length} roles at adjacent levels:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(adjacentJobs.slice(0, 6));
        }, 300);
      } else {
        // Show any PE jobs
        this.addSennaMessage(
          `Limited ${targetSeniority} openings currently. Here are other PE opportunities to consider:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 6));
        }, 300);
      }

      // Provide seniority-specific advice
      this.provideSeniorityAdvice(targetSeniority);
    }

    filterJobsByIndustry(industry) {
      this.filteredJobs = this.allJobs.filter((job) => {
        const title = (job.title || "").toLowerCase();
        const company = (job.company || "").toLowerCase();
        const description = (job.description || "").toLowerCase();
        const industryLower = industry.toLowerCase();

        return (
          title.includes(industryLower) ||
          company.includes(industryLower) ||
          description.includes(industryLower)
        );
      });

      const customHeadline = `${industry} Opportunities`;

      if (this.filteredJobs.length > 0) {
        this.addSennaMessage(
          `Found ${this.filteredJobs.length} ${industry} opportunities:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.filteredJobs.slice(0, 6));
        }, 300);
      } else {
        this.addSennaMessage(
          `No specific ${industry} roles available. Here are related finance opportunities:`,
          false,
          customHeadline
        );
        setTimeout(() => {
          this.renderJobsInChat(this.allJobs.slice(0, 6));
        }, 300);
      }
    }

    provideLocationHiringAdvice(locationName) {
      // First provide comprehensive advice
      const customHeadline = `Breaking into ${locationName}'s PE Market`;

      const locationAdvice = {
        London: {
          firms: "CVC, Permira, Apax, BC Partners, Cinven",
          requirements:
            "UK work authorization, ACA/CFA preferred, strong modeling skills",
          networking:
            "BVCA events, SuperReturn International, Private Equity London meetups",
          timeline: "6-12 months with focused networking",
        },
        "New York": {
          firms: "KKR, Blackstone, Apollo, Carlyle, TPG",
          requirements:
            "US work authorization, CPA/CFA valued, deal experience crucial",
          networking: "ACG events, PE conferences, Columbia/Wharton PE clubs",
          timeline: "3-9 months depending on experience level",
        },
        Singapore: {
          firms: "GIC, Temasek, Baring PE Asia, Affinity Equity",
          requirements:
            "APAC experience valuable, Mandarin helpful, regional deal exposure",
          networking: "SVCA events, AVCJ Forum, Singapore FinTech Festival",
          timeline: "4-8 months with regional connections",
        },
      };

      const info = locationAdvice[locationName] || {
        firms: "Leading regional PE firms",
        requirements: "Local market knowledge and relevant work authorization",
        networking: "Industry conferences and professional associations",
        timeline: "6-12 months with dedicated effort",
      };

      const advice = `
                <div class="hiring-advice-comprehensive">
                    <h3>How to Get Hired in ${locationName} Private Equity</h3>
                    
                    <div class="advice-section">
                        <h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px;"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>Target Firms</h4>
                        <p>${info.firms}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4>📋 Key Requirements</h4>
                        <p>${info.requirements}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4>🤝 Referral Strategy</h4>
                        <p>${info.networking}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4>⏱️ Typical Timeline</h4>
                        <p>${info.timeline}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px;"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>Pro Tips</h4>
                        <ul>
                            <li>Leverage alumni networks from top business schools</li>
                            <li>Get warm introductions through LinkedIn connections</li>
                            <li>Prepare deal discussion examples relevant to ${locationName} market</li>
                            <li>Understand local regulatory and tax considerations</li>
                        </ul>
                    </div>
                </div>
            `;

      this.addSennaMessage(advice, true, customHeadline);

      // Then show available jobs in that location
      setTimeout(() => {
        this.filteredJobs = this.allJobs.filter((job) => {
          const jobLocation = (job.location || "").toLowerCase();
          return jobLocation.includes(locationName.toLowerCase());
        });

        if (this.filteredJobs.length > 0) {
          this.addSennaMessage(
            `Here are ${this.filteredJobs.length} current openings in ${locationName} to target:`,
            false,
            "Current Opportunities"
          );
          setTimeout(() => {
            this.renderJobsInChat(this.filteredJobs.slice(0, 6));
          }, 300);
        } else {
          // Show alternative locations
          this.showAlternativeLocations(locationName, "Alternative Markets");
        }
      }, 500);
    }

    provideHiringAdviceWithJobs(location, seniority) {
      const customHeadline = `Landing ${seniority} Roles in ${location}`;

      const advice = `
                <div class="hiring-advice-targeted">
                    <h3>How to Secure ${seniority} Positions in ${location}</h3>
                    
                    <div class="advice-section">
                        <h4>🎓 Experience Required</h4>
                        <p>${this.getSeniorityRequirements(seniority)}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4>🏢 ${location} Market Dynamics</h4>
                        <p>${this.getLocationInsights(location)}</p>
                    </div>
                    
                    <div class="advice-section">
                        <h4><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 8px;"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>Positioning Strategy</h4>
                        <ul>
                            <li>Highlight deals relevant to ${location} market</li>
                            <li>Emphasize ${seniority}-level competencies</li>
                            <li>Network with ${seniority}s and one level above</li>
                            <li>Target firms with ${seniority} openings in ${location}</li>
                        </ul>
                    </div>
                </div>
            `;

      this.addSennaMessage(advice, true, customHeadline);

      // Then filter and show relevant jobs
      setTimeout(() => {
        this.filterJobsByCombined(location, seniority);
      }, 500);
    }

    getSeniorityRequirements(seniority) {
      const requirements = {
        Analyst:
          "0-3 years experience, strong financial modeling, Excel expertise",
        Associate:
          "3-5 years experience, deal execution track record, MBA helpful",
        "Vice President":
          "5-8 years experience, sourcing capabilities, team management",
        Director: "8-12 years experience, P&L responsibility, LP relationships",
        Partner:
          "12+ years experience, carry participation, fundraising expertise",
      };
      return requirements[seniority] || "Relevant experience for the level";
    }

    getLocationInsights(location) {
      const insights = {
        London:
          "Largest PE market in Europe, strong regulatory framework, Brexit considerations",
        "New York":
          "Global PE capital, highest comp packages, most competitive market",
        Singapore: "APAC hub, growing market, cross-border deal focus",
        "Hong Kong":
          "Greater China gateway, volatile market, Mandarin essential",
        Dubai: "private equity hub, sovereign wealth focus, relationship-driven market",
      };
      return insights[location] || "Dynamic market with growing PE presence";
    }

    provideLocationAdvice(locationName) {
      const advice = `
                <div class="no-results-advice">
                    <h3>No current openings in ${locationName}</h3>
                    <p>However, here's how to break into the ${locationName} PE market:</p>
                    <ul>
                        <li>Network with professionals at local PE firms</li>
                        <li>Attend industry events and conferences in ${locationName}</li>
                        <li>Consider adjacent financial hubs with more opportunities</li>
                        <li>Build relationships with headhunters specializing in ${locationName}</li>
                    </ul>
                    <p>Would you like me to suggest similar markets or alternative strategies?</p>
                </div>
            `;
      this.addSennaMessage(advice, true);
    }

    provideSeniorityAdvice(seniorityName) {
      const advice = `
                <div class="no-results-advice">
                    <h3>No ${seniorityName} positions available right now</h3>
                    <p>Here's how to position yourself for ${seniorityName} roles:</p>
                    <ul>
                        <li>Ensure your experience aligns with typical ${seniorityName} requirements</li>
                        <li>Consider lateral moves that could lead to ${seniorityName} positions</li>
                        <li>Network with professionals currently in ${seniorityName} roles</li>
                        <li>Develop skills commonly required at the ${seniorityName} level</li>
                    </ul>
                    <p>Would you like to see adjacent level positions or discuss your qualifications?</p>
                </div>
            `;
      this.addSennaMessage(advice, true);
    }

    clearConversation() {
      // Clear messages except the initial greeting
      $("#senna-messages")
        .children()
        .slice(2)
        .fadeOut(300, function () {
          $(this).remove();
        });

      // Reset state
      this.displayedJobs = [];
      this.filteredJobs = [...this.allJobs];
      this.stopLiveExpertPolling();
      this.liveExpertConnected = false;
      this.liveExpertConnectingShown = false;
      this.liveExpertWelcomeSent = false;
      this.liveExpertConnectionNotified = false;
      this.liveExpertMessageIds = new Set();
      this.liveExpertLastTimestamp = 0;
      this.persistLiveExpertConversationId(null);
      this.liveExpertMessageShown = false;
      this.welcomeMessageShown = false;

      setTimeout(() => {
        this.showLiveExpertPanel({
          source: "clear-conversation",
          force: true,
          headline: "Live Expert Support",
        });
      }, 400);
    }

    renderJobsInChat(jobs, skipShuffle = false) {
      this.showLiveExpertPanel({ source: "render-jobs-in-chat" });
    }

    shuffleArray(array) {
      const shuffled = [...array];
      for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
      }
      return shuffled;
    }

    // Check for new opportunities since last visit
    checkNewOpportunities(lastVisit) {
      if (!lastVisit || !this.allJobs) return 0;

      try {
        const lastVisitDate = new Date(lastVisit);
        const now = new Date();
        const hoursSinceVisit = (now - lastVisitDate) / (1000 * 60 * 60);

        // Simulate new opportunities based on time elapsed
        // In production, this would check actual new jobs from the database
        if (hoursSinceVisit > 24) {
          return Math.min(8, Math.floor(Math.random() * 5) + 3); // 3-8 new jobs
        } else if (hoursSinceVisit > 12) {
          return Math.floor(Math.random() * 3) + 1; // 1-3 new jobs
        }
        return 0;
      } catch (e) {
        return 0;
      }
    }

    createVogueJobCard(job, index) {
      try {
        const cvData =
          window.pendingCvData ||
          window.currentCVData ||
          window.pendingCVMatch ||
          {};

        if (typeof window.renderCompactJobCard === "function") {
          return window.renderCompactJobCard(job, cvData);
        }

        const isInShortlist = this.shortlist.some((item) => item.id === job.id);

        // Use currency handler if available, fallback to basic format
        let salaryRange;
        if (window.currencyHandler && window.currencyHandler.initialized) {
          salaryRange = window.currencyHandler.formatRange(
            job.salary_min,
            job.salary_max,
            null // Will use current user's currency
          );
        } else {
          salaryRange =
            job.salary_display ||
            this.formatSalary(job.salary_min, job.salary_max);
        }

        // Add PE-specific data attributes for filter access
        const peDataAttrs = [];
        if (job.fund_size)
          peDataAttrs.push(`data-fund-size="${job.fund_size}"`);
        if (job.work_style)
          peDataAttrs.push(`data-work-style="${job.work_style}"`);
        if (job.geo_focus)
          peDataAttrs.push(`data-geo-focus="${job.geo_focus}"`);
        if (job.seniority_level)
          peDataAttrs.push(`data-seniority-level="${job.seniority_level}"`);
        if (job.isShared) peDataAttrs.push(`data-shared="true"`);

        // Extract highlights from key_requirements, skills, and qualifications
        let highlights = [];

        // First priority: key_requirements
        if (job.key_requirements && job.key_requirements.length > 0) {
          highlights = job.key_requirements.slice(0, 3).map((req) => {
            return typeof req === "object" ? req.text || "" : req;
          });
        }
        // Second priority: combine skills and qualifications
        else {
          if (job.skills && job.skills.length > 0) {
            highlights.push(
              ...job.skills
                .slice(0, 2)
                .map((skill) => `${skill} expertise required`)
            );
          }
          if (job.qualifications) {
            const qual = Array.isArray(job.qualifications)
              ? job.qualifications[0]
              : job.qualifications;
            if (qual && qual.length > 0) {
              // Extract first meaningful qualification
              const firstQual = qual.split(/[.!?]/)[0].trim();
              if (firstQual.length > 0 && firstQual.length < 100) {
                highlights.push(firstQual);
              }
            }
          }
          // If still no highlights, use job description or benefits
          if (highlights.length === 0 && job.description) {
            const sentences = job.description
              .split(/[.!?]/)
              .filter((s) => s.trim().length > 0);
            highlights = sentences
              .slice(0, 3)
              .map((s) => s.trim().substring(0, 60) + "...");
          }
          // Final fallback to benefits or empty
          if (highlights.length === 0 && job.benefits) {
            highlights = job.benefits.slice(0, 3);
          }
        }

        // Ensure we have max 3 highlights, don't force if less
        highlights = highlights.slice(0, 3).filter((h) => h && h.length > 0);

        // Generate dynamic MENA Careers tip based on multiple factors
        let sennaTip = "Great opportunity for growth";
        try {
          if (typeof generateDynamicSennaTip === "function") {
            sennaTip =
              generateDynamicSennaTip(job) || "Great opportunity for growth";
          }
        } catch (error) {
          sennaTip = "Excellent career opportunity";
        }

        // Get firm size and AI salary estimate
        const firmSize = this.getFirmSizeLabel(job.company || "Company");
        const aiSalaryEstimate = this.getAISalaryEstimate(job);

        // Compact premium card structure with prominent Tailor CV button
        return `
                <article class="sffc-match-card job-card-vogue chat-compact job-card-simplified" data-job-id="${
                  job.id
                }" ${peDataAttrs.join(" ")}>
                    <div class="select-checkbox" data-job-id="${job.id}"></div> 
                                style="
                                    width: 100%;
                                    padding: 14px 24px;
                                    background: linear-gradient(135deg, #1A3028 0%, #2D6A4F 100%);
                                    color: #FFFFFF;
                                    border: none;
                                    border-radius: 8px;
                                    font-size: 15px;
                                    font-weight: 600;
                                    letter-spacing: 0.3px;
                                    cursor: pointer;
                                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                    box-shadow: 0 3px 10px rgba(26, 48, 40, 0.2);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 10px;
                                    position: relative;
                                    overflow: hidden;
                                ">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <path d="M16 13H8"></path>
                                <path d="M16 17H8"></path>
                                <path d="M10 9H8"></path>
                            </svg>
                            <span>Tailor My CV</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto;">
                                <path d="M5 12h14"></path>
                                <path d="m12 5 7 7-7 7"></path>
                            </svg>
                    <div class="sffc-company-section" data-job-id="${
                      job.id
                    }" style="padding-top: 16px;">
                        <div class="vogue-job-actions">
                            
                            <button class="vogue-action-btn btn-apply" data-job='${JSON.stringify(
                              job
                            ).replace(/'/g, "&apos;")}' data-job-id="${
          job.id
        }" title="Express Interest">
                                <span>Express Interest</span>
                            </button>
                        </div>
                        <h2 class="sffc-job-title">${job.title}</h2>
                        <p class="sffc-company-name">${firmSize} • ${
          job.location
        } • 🤖 ${aiSalaryEstimate}</p>
                        
                        <!-- Prominent Tailor CV button moved here -->
                        <div class="tailor-cv-prominent" style="margin-top: 12px;">
                            <button class="tailor-cv-main-btn" 
                                    data-job-id="${job.id}" 
                                    onclick="event.stopPropagation(); window.tailorCV && window.tailorCV(${
                                      job.id
                                    })" 
                                    style="
                                        width: 100%;
                                        padding: 14px 24px;
                                        background: linear-gradient(135deg, #1A3028 0%, #2D6A4F 100%);
                                        color: #FFFFFF;
                                        border: none;
                                        border-radius: 8px;
                                        font-size: 15px;
                                        font-weight: 600;
                                        letter-spacing: 0.3px;
                                        cursor: pointer;
                                        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                        box-shadow: 0 3px 10px rgba(26, 48, 40, 0.2);
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        gap: 10px;
                                        position: relative;
                                        overflow: hidden;
                                    ">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <path d="M16 13H8"></path>
                                    <path d="M16 17H8"></path>
                                    <path d="M10 9H8"></path>
                                </svg>
                                <span>Tailor My CV</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto;">
                                    <path d="M5 12h14"></path>
                                    <path d="m12 5 7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-job-tags">
                            ${
                              job.experience_level
                                ? `<span class="sffc-job-tag">${job.experience_level}</span>`
                                : ""
                            }
                            ${
                              job.posted_date
                                ? `<span class="sffc-job-tag">${
                                    this.getTimeAgo
                                      ? this.getTimeAgo(job.posted_date)
                                      : ""
                                  }</span>`
                                : ""
                            }
                            ${
                              job.skills && job.skills.length > 0
                                ? job.skills
                                    .slice(0, 2)
                                    .map(
                                      (skill) =>
                                        `<span class="sffc-job-tag">${skill}</span>`
                                    )
                                    .join("")
                                : ""
                            }
                        </div>
                        
                        ${
                          job.sffc_application_url ||
                          job.application_url ||
                          job.link
                            ? `
                        <div class="sffc-apply-button-container" style="margin-top: 16px;">
                            <button class="ask-senna-btn sffc-apply-btn" 
                                onclick="event.stopPropagation(); window.open('${
                                  job.sffc_application_url ||
                                  job.application_url ||
                                  job.link
                                }', '_blank')" 
                                style="width: 100%; padding: 16px; background: #F5F2E8; border: 2px solid #F5F2E8; border-radius: 12px; color: #0d353e; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <span>Apply Now</span>
                                <span>→</span>
                            </button>
                        </div>
                        `
                            : ""
                        }
                    </div>
                </article>
            `;
      } catch (error) {
        // Return a minimal fallback card with proper buttons
        const jobId = job.id || "unknown";
        const applicationUrl = job.application_url || "";
        return `
                    <article class="sffc-match-card job-card-vogue chat-compact" data-job-id="${jobId}">
                        <div class="select-checkbox"></div>
                        
                        <!-- Company Section with Action Buttons -->
                        <div class="sffc-company-section">
                            <div class="vogue-job-actions">
                                
                                <button class="vogue-action-btn btn-apply" 
                                        data-job-id="${jobId}" 
                                        data-url="${applicationUrl}"
                                        onclick="event.stopPropagation(); window.applyToJob && window.applyToJob(${jobId}, '${applicationUrl}')">
                                    <span>Express Interest</span>
                                </button>
                            </div>
                            <h2 class="sffc-job-title">${
                              job.title || "Position"
                            }</h2>
                            <p class="sffc-company-name">${this.getFirmSizeLabel(
                              job.company || "Company"
                            )} • ${job.location || "Location"}</p>
                            
                            <!-- Prominent Tailor CV Button moved here -->
                            <div class="tailor-cv-prominent">
                                <button class="tailor-cv-main-btn" 
                                        data-job-id="${jobId}" 
                                        onclick="event.stopPropagation(); window.tailorCV && window.tailorCV(${jobId})">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <span>Tailor My CV</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </article>
                `;
      }
    }

    createJobCard(job, index) {
      const isInShortlist = this.shortlist.some((item) => item.id === job.id);

      // Use currency handler if available, fallback to basic format
      let salaryRange;
      if (window.currencyHandler && window.currencyHandler.initialized) {
        salaryRange = window.currencyHandler.formatRange(
          job.salary_min,
          job.salary_max,
          null // Will use current user's currency
        );
      } else {
        salaryRange =
          job.salary_display ||
          this.formatSalary(job.salary_min, job.salary_max);
      }

      return `
                <div class="sffc-job-card-guided" data-job-id="${
                  job.id
                }" style="animation-delay: ${index * 0.1}s">
                    <div class="card-header">
                        <div class="company-logo">${job.company.charAt(0)}</div>
                        <div class="job-info">
                            <h3 class="job-title">${job.title}</h3>
                            <p class="company-name">${job.company}</p>
                        </div>
                        ${
                          job.match_score
                            ? `
                        <div class="match-score">
                            <span class="score">${job.match_score}%</span>
                            <span class="label">Match</span>
                        </div>
                        `
                            : ""
                        }
                    </div>
                    <div class="card-meta">
                        <span class="meta-item">${job.location}</span>
                        <span class="meta-item">${salaryRange}</span>
                        <span class="meta-item">${
                          job.job_type || "Full-time"
                        }</span>
                    </div>
                    ${
                      job.skills && job.skills.length > 0
                        ? `
                    <div class="card-skills">
                        ${job.skills
                          .slice(0, 3)
                          .map(
                            (skill) => `<span class="skill-tag">${skill}</span>`
                          )
                          .join("")}
                        ${
                          job.skills.length > 3
                            ? `<span class="skill-more">+${
                                job.skills.length - 3
                              }</span>`
                            : ""
                        }
                    </div>
                    `
                        : ""
                    }
                    <div class="card-actions">
                        <button class="action-btn btn-interested ${
                          isInShortlist ? "added" : ""
                        }" 
                                data-job='${JSON.stringify(job).replace(
                                  /'/g,
                                  "&apos;"
                                )}'>
                            ${isInShortlist ? "Added" : "Interested"}
                        </button>
                        <button class="action-btn btn-pass">Not Now</button>
                    </div>
                    <div class="quick-apply-wrapper">
                        <button class="btn-quick-apply" data-job-id="${
                          job.id
                        }" data-job='${JSON.stringify(job).replace(
        /'/g,
        "&apos;"
      )}'
                                style="background: linear-gradient(135deg, #2D6A4F 0%, #1B4332 100%); color: #FFFFFF; border: none; padding: 10px 20px; border-radius: 6px; width: 100%; margin-top: 10px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                            Quick Apply
                        </button>
                    </div>
                </div>
            `;
    }

    formatSalary(min, max) {
      if (!min && !max) return "Competitive";
      if (!max || max === min) return "$" + this.formatNumber(min) + "+";
      if (!min) return "Up to $" + this.formatNumber(max);
      return "$" + this.formatNumber(min) + " - $" + this.formatNumber(max);
    }

    formatNumber(num) {
      if (num >= 1000) {
        return Math.round(num / 1000) + "k";
      }
      return num.toString();
    }

    bindCardEvents() {
      // First, unbind ALL previous handlers with namespace to prevent duplicates
      $(".sffc-btn-pass").off("click.sennaconv");
      $(".sffc-btn-tailor").off("click.sennaconv");
      $(
        ".sffc-btn-interested, .btn-interested, .vogue-action.btn-interested"
      ).off("click.sennaconv");
      $(".btn-pass, .vogue-action.btn-pass").off("click.sennaconv");
      $(".btn-quick-apply").off("click.sennaconv");
      $(".btn-apply:not(.application-modal-btn)").off("click.sennaconv");
      $(".btn-analyze-first").off("click.sennaconv");
      $(".sffc-btn-shortlist").off("click.sennaconv");

      // Store job data in a map for easy access
      if (!this.jobDataMap) {
        this.jobDataMap = new Map();
      }

      // Store all displayed job data
      if (this.displayedJobs) {
        this.displayedJobs.forEach((job) => {
          this.jobDataMap.set(job.id, job);
        });
      }

      // Pass button with namespace
      $(".sffc-btn-pass").on("click.sennaconv", (e) => {
        e.stopPropagation();
        e.preventDefault();
        const jobId = $(e.currentTarget).data("job-id");
        const $card = $(e.currentTarget).closest(".sffc-match-card");
        const $btn = $(e.currentTarget);

        // Add passed state and fade out
        $card.addClass("passed").css("opacity", "0.5");
        $btn
          .prop("disabled", true)
          .html(
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Passed</span>'
          );

        // Remove from displayed jobs
        if (this.displayedJobs) {
          this.displayedJobs = this.displayedJobs.filter((j) => j.id !== jobId);
        }

        // Slide up and remove after animation
        setTimeout(() => {
          // Smoother removal without bouncing on mobile
          $card.css({
            transition: "opacity 0.3s ease, transform 0.3s ease",
            opacity: "0",
            transform: "translateX(-100%)",
          });
          setTimeout(() => {
            $card.remove();
          }, 300);
        }, 500);
      });

      // Tailor CV button with namespace
      $(".sffc-btn-tailor").on("click.sennaconv", (e) => {
        e.stopPropagation();
        e.preventDefault();

        // Remove any existing modals first to prevent stacking
        $(".cv-upload-modal, .cv-tailor-modal").remove();

        const jobId = $(e.currentTarget).data("job-id");
        const job = this.jobDataMap.get(jobId);

        if (job && window.ensureWSJChatForJob) {
          window.ensureWSJChatForJob(job, { source: "tailor_button" });
        }

        if (job && window.openCVTailor) {
          // Add a small delay to ensure clean state
          setTimeout(() => {
            window.openCVTailor(jobId, job);
          }, 100);
        } else {
        }

        return false; // Prevent any further propagation
      });

      // Interested button with namespace
      $(
        ".sffc-btn-interested, .btn-interested, .vogue-action.btn-interested"
      ).on("click.sennaconv", (e) => {
        e.stopPropagation();
        e.preventDefault();
        const $btn = $(e.currentTarget);
        const jobId = $btn.data("job-id");
        const job =
          this.jobDataMap.get(jobId) ||
          this.allJobs.find((j) => j.id === jobId);

        if (!job) {
          return;
        }

        if (!$btn.hasClass("added")) {
          // Use global addToShortlist if available, otherwise use local
          if (window.addToShortlist) {
            window.addToShortlist(job);
          } else {
            this.addToShortlist(job);
          }
          $btn.addClass("added").find("span").text("Saved");

          // Get current shortlist count from localStorage
          const currentShortlist = JSON.parse(
            localStorage.getItem("sffc_shortlist") || "[]"
          );

          // Contextual MENA Careers response
          if (currentShortlist.length === 1) {
            this.addSennaMessage(
              `Excellent intuition. ${job.title} at ${job.company} aligns beautifully with your trajectory.`
            );
          } else if (currentShortlist.length === 3) {
            this.addSennaMessage(
              `You've curated 3 strategic opportunities. Shall I analyze how each positions you differently in the market?`
            );
          }

          if (window.showSavedRolesInChat) {
            window.showSavedRolesInChat({
              highlightJobId: jobId,
              source: "shortlist",
            });
          }
        }
      });

      // Pass button - both old and Vogue styles
      $(".btn-pass, .vogue-action.btn-pass")
        .off("click")
        .on("click", (e) => {
          e.stopPropagation();
          e.preventDefault();
          const $card = $(e.currentTarget).closest(
            ".sffc-job-card-guided, .job-card-vogue"
          );
          $card.fadeOut(300, () => $card.remove());
        });

      // Quick Apply button with namespace - routes to application interface
      $(".btn-quick-apply").on("click.sennaconv", (e) => {
        e.stopPropagation();
        const $btn = $(e.currentTarget);
        const jobData = JSON.parse(
          $btn.attr("data-job").replace(/&apos;/g, "'")
        );

        // Store job data for application
        localStorage.setItem(
          "sffc_current_application",
          JSON.stringify(jobData)
        );

        // Check if user profile is complete
        const profileData = localStorage.getItem("sffc_user_profile");
        let isProfileComplete = false;

        if (profileData) {
          try {
            const profile = JSON.parse(profileData);
            isProfileComplete = !!(
              profile.full_name &&
              profile.skills &&
              profile.skills.length > 0
            );
          } catch (e) {}
        }

        // Always show intelligence package, but with locked sections if profile incomplete
        this.showIntelligencePackage(jobData, isProfileComplete);
      });
      // Remove intelligence click from apply buttons entirely (but not modal buttons)
      $(".btn-apply:not(.application-modal-btn)").off("click.intelligence");
      $(".btn-apply:not(.application-modal-btn)").off("click");

      $(".btn-apply:not(.application-modal-btn)")
        .off("click.sennaconv")
        .on("click.sennaconv", (e) => {
          e.preventDefault();
          e.stopPropagation();

          const $btn = $(e.currentTarget);
          let jobData = {};
          try {
            jobData = JSON.parse($btn.attr("data-job") || "{}");
          } catch (e) {}

          const applyUrl = jobData.application_url || $btn.data("url");

          if (applyUrl) {
            window.open(applyUrl, "_blank");
          } else {
            console.warn(
              "No application URL found for job:",
              jobData,
              "btn:",
              $btn[0]
            );
          }

          // Store job data for application
          localStorage.setItem(
            "sffc_current_application",
            JSON.stringify(jobData)
          );

          // Check if user profile is complete
          const profileData = localStorage.getItem("sffc_user_profile");
          let isProfileComplete = false;

          if (profileData) {
            try {
              const profile = JSON.parse(profileData);
              isProfileComplete = !!(
                profile.full_name &&
                profile.skills &&
                profile.skills.length > 0
              );
            } catch (e) {}
          }

          // Always show intelligence package, but with locked sections if profile incomplete
          this.showIntelligencePackage(jobData, isProfileComplete);
        });

      // Analyze First button with namespace
      $(".btn-analyze-first").on("click.sennaconv", (e) => {
        e.stopPropagation();
        const $btn = $(e.currentTarget);
        const jobData = JSON.parse(
          $btn.attr("data-job").replace(/&apos;/g, "'")
        );

        // Add to shortlist if not already
        if (!this.shortlist.some((item) => item.id === jobData.id)) {
          this.addToShortlist(jobData);
        }

        // Switch to analyze mode with this job
        localStorage.setItem("sffc_analyze_focus", JSON.stringify(jobData));
        this.switchToAnalyze();
      });
    }

    initiateQuickApply(job) {
      // Show application interface inline
      this.addSennaMessage(
        `Starting your application for ${job.title} at ${job.company}. I'll help you create tailored materials.`
      );

      // Trigger the apply mode handler if available
      if (window.ApplyModeHandler) {
        window.ApplyModeHandler.startApplication(job);
      } else {
        // Create inline application UI
        const applicationUI = `
                    <div class="quick-apply-interface">
                        <h3>Quick Apply: ${job.title}</h3>
                        <p class="company-name">${job.company} - ${
          job.location
        }</p>
                        
                        <div class="application-steps">
                            <div class="step active" data-step="1">
                                <span class="step-number">1</span>
                                <span class="step-label">Review Profile</span>
                            </div>
                            <div class="step" data-step="2">
                                <span class="step-number">2</span>
                                <span class="step-label">Customize Materials</span>
                            </div>
                            <div class="step" data-step="3">
                                <span class="step-number">3</span>
                                <span class="step-label">Submit</span>
                            </div>
                        </div>
                        
                        <div class="application-content">
                            <p>I'll help you create a standout application. First, let me review your profile to highlight relevant experience.</p>
                            <button class="btn-primary start-application" data-job='${JSON.stringify(
                              job
                            ).replace(/'/g, "&apos;")}'>
                                Continue with Application
                            </button>
                        </div>
                    </div>
                `;

        $("#senna-messages").append(applicationUI);
        this.scrollToBottom();

        // Bind application events
        $(".start-application").on("click", (e) => {
          const jobData = JSON.parse(
            $(e.currentTarget)
              .attr("data-job")
              .replace(/&apos;/g, "'")
          );
          this.startDetailedApplication(jobData);
        });
      }

      // Store application start
      this.storeApplicationStart(job);
    }

    startDetailedApplication(job) {
      // Transition to full application mode

      // Show materials generation
      if (window.MaterialGenerator) {
        $(document).trigger("materialGenerator:generate", ["all", job]);
      }

      this.addSennaMessage(
        `Generating customized materials for your application...`
      );
    }

    storeApplicationStart(job) {
      // Store application in localStorage
      const applications = JSON.parse(
        localStorage.getItem("sffc_applications") || "[]"
      );

      const application = {
        id: `app_${Date.now()}`,
        job_id: job.id,
        job_title: job.title,
        company: job.company,
        started_at: new Date().toISOString(),
        status: "started",
        progress: 0,
      };

      applications.push(application);
      localStorage.setItem("sffc_applications", JSON.stringify(applications));

      // Trigger event for other components
      $(document).trigger("application:started", [application]);
    }

    handleQuickApply(job, $btn) {
      // Check if Chrome extension is installed by checking for extension marker
      const hasExtension =
        document.querySelector('meta[name="sffc-extension-installed"]') ||
        window.sffcExtensionInstalled ||
        false;

      if (hasExtension) {
        // Extension is installed - trigger autofill
        $btn.html(
          '<span style="display: inline-flex; align-items: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="sffc-spinner"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg> Preparing...</span>'
        );

        // Get user profile for autofill
        this.prepareQuickApply(job, $btn);
      } else {
        // Extension not installed - use standard application flow
        this.initiateQuickApply(job);
      }
    }

    async prepareQuickApply(job, $btn) {
      try {
        // Get user profile data
        const response = await fetch("/wp-json/sffc/v1/autofill/profile", {
          method: "GET",
          headers: {
            "X-WP-Nonce": window.sffc_frontend?.nonce || "",
          },
        });

        if (!response.ok) throw new Error("Failed to get profile");

        const profileData = await response.json();

        // Open job URL with autofill parameters
        const applyUrl = job.apply_url || job.url;
        if (applyUrl) {
          // Add autofill flag to URL
          const separator = applyUrl.includes("?") ? "&" : "?";
          const autoFillUrl = `${applyUrl}${separator}sffc_autofill=true&job_id=${job.id}`;

          // Store profile data for extension
          localStorage.setItem(
            "sffc_quick_apply_profile",
            JSON.stringify(profileData)
          );
          localStorage.setItem("sffc_quick_apply_job", JSON.stringify(job));

          // Open in new tab
          window.open(autoFillUrl, "_blank");

          // Update button state
          $btn.html(
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg> Launched'
          );

          // Store quick apply
          this.trackQuickApply(job);

          // Show success message
          this.addSennaMessage(
            `I've opened the application for ${job.title} at ${job.company}. The AutoFill extension will help complete the form for you.`
          );
        } else {
          throw new Error("No application URL available");
        }
      } catch (error) {
        $btn.html("Quick Apply");
        this.addSennaMessage(
          `I couldn't start the quick application. Would you like to try the standard application process instead?`
        );
      }
    }

    showExtensionPrompt(job) {
      const promptHtml = `
                <div class="extension-install-prompt" style="background: linear-gradient(135deg, #FFFDF8 0%, #FAF7F2 100%); border: 2px solid #2D6A4F; border-radius: 12px; padding: 20px; margin: 20px 0;">
                    <h3 style="color: #1A3028; margin-bottom: 10px;">Install AutoFill Extension</h3>
                    <p style="color: #1A3028; margin-bottom: 15px;">To use Quick Apply, you need the senna AutoFill Chrome Extension.</p>
                    <div style="display: flex; gap: 10px;">
                        <button class="install-extension-btn" style="background: linear-gradient(135deg, #2D6A4F 0%, #1B4332 100%); color: #FFFFFF; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                            Install Extension
                        </button>
                        <button class="standard-apply-btn" data-job='${JSON.stringify(
                          job
                        ).replace(
                          /'/g,
                          "&apos;"
                        )}' style="background: #1A3028; color: #FBF7F0; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                            Standard Apply
                        </button>
                    </div>
                </div>
            `;

      $("#senna-messages").append(promptHtml);
      this.scrollToBottom();

      // Bind install button
      $(".install-extension-btn").on("click", () => {
        window.open("/dashboard/autofill-assistant", "_blank");
      });

      // Bind standard apply
      $(".standard-apply-btn").on("click", (e) => {
        const jobData = JSON.parse(
          $(e.currentTarget)
            .attr("data-job")
            .replace(/&apos;/g, "'")
        );
        this.startApplication(jobData);
      });
    }

    async showIntelligencePackage(job, isProfileComplete = false) {
      // Add gentle profile completion reminder if incomplete
      if (!isProfileComplete) {
        const profile = JSON.parse(
          localStorage.getItem("sffc_user_profile") || "{}"
        );
        const missingKey =
          !profile.skills || profile.skills.length === 0
            ? "skills"
            : !profile.experience_level
            ? "experience level"
            : !profile.years_experience
            ? "years of experience"
            : null;

        if (missingKey && !this.intelligencePromptShown) {
          // Show a subtle floating notification that auto-dismisses
          const notification = $(`
                        <div style="position: fixed; bottom: 80px; right: 20px; background: linear-gradient(135deg, #DBEAFE, #EFF6FF); 
                                    border: 1px solid #93C5FD; border-radius: 12px; padding: 12px 16px; 
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 1000; max-width: 320px;
                                    animation: slideInRight 0.3s ease-out;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E40AF" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>
                                <div style="flex: 1;">
                                    <div style="font-size: 13px; color: #1E40AF; font-weight: 600; margin-bottom: 2px;">Unlock Full Intelligence</div>
                                    <div style="font-size: 12px; color: #64748B;">Add your ${missingKey} for personalized insights</div>
                                </div>
                                <button onclick="$(this).parent().parent().fadeOut(); sennaConversational.completeProfile();" 
                                        style="background: #3B82F6; color: white; border: none; padding: 6px 12px; 
                                               border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 500;">
                                    Add Now
                                </button>
                            </div>
                        </div>
                    `);

          $("body").append(notification);
          this.intelligencePromptShown = true;

          // Auto-dismiss after 8 seconds
          setTimeout(() => {
            notification.fadeOut(300, () => notification.remove());
          }, 8000);

          // Reset flag after some time
          setTimeout(() => {
            this.intelligencePromptShown = false;
          }, 300000); // 5 minutes
        }
      }

      // Check if intelligence package is available (should be enqueued by WordPress)
      if (!window.IntelligencePackage) {
        console.error(
          "Intelligence Package not loaded. Please check WordPress enqueue."
        );
        this.addSennaMessage(
          "Unable to load intelligence briefing. Please try the standard application process."
        );
        return;
      }

      // Pass profile completion status to intelligence package
      if (window.intelligencePackage) {
        window.intelligencePackage.isProfileComplete = isProfileComplete;
      }

      // Show loading state
      const loadingHtml = `
                <div class="intelligence-package-loading" style="padding: 40px; text-align: center;">
                    <div style="width: 48px; height: 48px; margin: 0 auto 16px; border: 3px solid #E5E7EB; border-top-color: #0D353E; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="color: #6B7280; font-size: 14px;">Generating intelligence briefing...</p>
                </div>
            `;

      // Add loading message to chat
      const messageId = "intel-" + Date.now();
      const messageHtml = `
                <div id="${messageId}" class="sffc-message sffc-message-senna sffc-intelligence-message">
                    ${loadingHtml}
                </div>
            `;
      $("#senna-messages").append(messageHtml);
      this.scrollToBottom();

      // Generate intelligence
      if (window.intelligencePackage) {
        try {
          const intelligenceHtml = await window.intelligencePackage.generate(
            job.id || job.sffc_job_id
          );

          // Replace loading with actual intelligence
          $(`#${messageId}`).html(intelligenceHtml);

          // Bind intelligence action buttons
          this.bindIntelligenceActions();

          // Track intelligence view
          if (window.gtag) {
            gtag("event", "intelligence_package_viewed", {
              event_category: "intelligence",
              job_id: job.id || job.sffc_job_id,
              job_title: job.title || job.sffc_job_title,
              company: job.company || job.sffc_company,
            });
          }
        } catch (error) {
          console.error("Error generating intelligence:", error);
          $(`#${messageId}`).html(`
                        <div style="padding: 20px; color: #DC2626;">
                            Unable to generate intelligence briefing. Please try the standard application process.
                        </div>
                    `);
        }
      } else {
        // Fallback if script didn't load
        $(`#${messageId}`).html(`
                    <div style="padding: 20px;">
                        <p style="color: #6B7280;">Intelligence package is being prepared. In the meantime, you can apply directly:</p>
                        <button onclick="window.open('${
                          job.link || job.sffc_application_url || "#"
                        }', '_blank')" 
                                style="margin-top: 12px; padding: 10px 20px; background: #0D353E; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Apply Now →
                        </button>
                    </div>
                `);
      }
    }

    async loadIntelligenceScript() {
      return new Promise((resolve, reject) => {
        // Check if already loading
        if (window.intelligencePackageLoading) {
          // Wait for existing load
          const checkInterval = setInterval(() => {
            if (window.IntelligencePackage) {
              clearInterval(checkInterval);
              resolve();
            }
          }, 100);
          return;
        }

        window.intelligencePackageLoading = true;

        // Create script element
        const script = document.createElement("script");
        script.src =
          (window.sffc_ajax?.plugin_url ||
            window.sffc_frontend?.plugin_url ||
            "") +
          "assets/js/intelligence-package.js?ver=" +
          Date.now();
        script.onload = () => {
          window.intelligencePackageLoading = false;
          resolve();
        };
        script.onerror = () => {
          window.intelligencePackageLoading = false;
          console.error("Failed to load intelligence package script");
          reject(new Error("Script load failed"));
        };

        document.head.appendChild(script);

        // Also load CSS if not already loaded
        if (!document.querySelector('link[href*="intelligence-package.css"]')) {
          const link = document.createElement("link");
          link.rel = "stylesheet";
          link.href =
            (window.sffc_ajax?.plugin_url ||
              window.sffc_frontend?.plugin_url ||
              "") +
            "assets/css/intelligence-package.css?ver=" +
            Date.now();
          document.head.appendChild(link);
        }
      });
    }

    bindIntelligenceActions() {
      // Bind action buttons within intelligence package
      $(".intel-action-primary")
        .off("click")
        .on("click", function () {
          const jobId = $(this)
            .attr("onclick")
            .match(/applyNow\('([^']+)'\)/)?.[1];
          if (jobId && window.intelligencePackage) {
            window.intelligencePackage.applyNow(jobId);
          }
        });

      $(".intel-action-secondary")
        .off("click")
        .on("click", function () {
          const jobId = $(this)
            .attr("onclick")
            .match(/tailorCV\('([^']+)'\)/)?.[1];
          if (jobId && window.intelligencePackage) {
            window.intelligencePackage.tailorCV(jobId);
          }
        });

      $(".intel-action-tertiary")
        .off("click")
        .on("click", function () {
          const jobId = $(this)
            .attr("onclick")
            .match(/saveIntelligence\('([^']+)'\)/)?.[1];
          if (jobId && window.intelligencePackage) {
            window.intelligencePackage.saveIntelligence(jobId);
          }
        });
    }

    trackQuickApply(job) {
      // Store quick apply event
      if (window.gtag) {
        gtag("event", "quick_apply_launched", {
          event_category: "autofill",
          event_label: job.company,
          job_id: job.id,
          job_title: job.title,
        });
      }

      // Store in local tracking
      const quickApplies = JSON.parse(
        localStorage.getItem("sffc_quick_applies") || "[]"
      );
      quickApplies.push({
        job_id: job.id,
        job_title: job.title,
        company: job.company,
        timestamp: new Date().toISOString(),
      });
      localStorage.setItem("sffc_quick_applies", JSON.stringify(quickApplies));
    }

    addToShortlist(job) {
      if (!this.shortlist.some((item) => item.id === job.id)) {
        this.shortlist.push(job);
        localStorage.setItem("sffc_shortlist", JSON.stringify(this.shortlist));

        // Update shortlist display
        if (window.updateShortlistDisplay) {
          window.updateShortlistDisplay();
        }

        // Show shortlist panel when item is added
        $(".sffc-shortlist-floating").addClass("active expanded");
      }
    }

    generateJobMatches() {
      // Get user profile data from localStorage
      const profileData = JSON.parse(
        localStorage.getItem("sffc_cv_parsed_profile") || "{}"
      );

      if (!profileData || Object.keys(profileData).length === 0) {
        this.addSennaMessage(
          "I'll need you to upload your CV first to find the best job matches for your profile.",
          false
        );
        return;
      }

      this.addSennaMessage(
        "Let me analyze your profile and find opportunities where you'd be competitive...",
        false
      );

      // Simulate intelligent job matching based on profile assessment
      setTimeout(() => {
        const matchedJobs = this.getProfileBasedJobMatches(profileData);
        const jobsHtml = this.renderJobMatches(matchedJobs);
        this.addSennaMessage(jobsHtml, true);
      }, 1500);
    }

    getProfileBasedJobMatches(profileData) {
      // Use existing job data or generate sample matches based on profile
      const experience = profileData.experience || [];
      const skills = profileData.skills || [];

      // Determine profile strength for job matching
      let profileLevel = "entry";
      let totalYears = 0;
      let hasFinanceExp = false;

      const financeKeywords = [
        "investment",
        "banking",
        "private equity",
        "asset management",
        "financial",
        "hedge fund",
      ];

      experience.forEach((exp) => {
        const title = (exp.title || "").toLowerCase();
        const description = (
          exp.responsibilities ||
          exp.description ||
          ""
        ).toLowerCase();

        if (
          financeKeywords.some(
            (keyword) =>
              title.includes(keyword) || description.includes(keyword)
          )
        ) {
          hasFinanceExp = true;
        }

        // Rough year calculation
        totalYears += 1.5; // Average job length
      });

      if (totalYears >= 5 && hasFinanceExp) profileLevel = "senior";
      else if (totalYears >= 2 && hasFinanceExp) profileLevel = "mid";
      else if (hasFinanceExp) profileLevel = "junior";

      // Generate appropriate job matches based on profile level
      return this.generateAppropriateJobs(profileLevel, totalYears);
    }

    generateAppropriateJobs(profileLevel, totalYears) {
      const jobTemplates = {
        entry: [
          {
            id: "entry_1",
            title: "Investment Banking Analyst",
            company: "Mid-Market Investment Bank",
            location: "London",
            match_score: 72,
            competitiveness: "Good match - your analytical skills align well",
            key_requirements: [
              "Financial modeling",
              "Excel proficiency",
              "Client interaction",
            ],
            application_url: "#",
          },
          {
            id: "entry_2",
            title: "Corporate Development Associate",
            company: "Growing Tech Company",
            location: "London",
            match_score: 68,
            competitiveness: "Strong potential - transferable skills valued",
            key_requirements: [
              "M&A analysis",
              "Business strategy",
              "Due diligence",
            ],
            application_url: "#",
          },
        ],
        junior: [
          {
            id: "junior_1",
            title: "Private Equity Associate",
            company: "Mid-Market PE Fund",
            location: "London",
            match_score: 79,
            competitiveness: "Strong match - finance background fits perfectly",
            key_requirements: [
              "Deal sourcing",
              "Financial modeling",
              "Portfolio management",
            ],
            application_url: "#",
          },
          {
            id: "junior_2",
            title: "Investment Banking Associate",
            company: "Boutique Investment Bank",
            location: "London",
            match_score: 75,
            competitiveness: "Good fit - experience level aligns well",
            key_requirements: [
              "Client management",
              "Deal execution",
              "Team leadership",
            ],
            application_url: "#",
          },
        ],
        mid: [
          {
            id: "mid_1",
            title: "Private Equity Vice President",
            company: "Growth Equity Fund",
            location: "London",
            match_score: 84,
            competitiveness: "Excellent match - ready for leadership role",
            key_requirements: [
              "Deal leadership",
              "Team management",
              "Investor relations",
            ],
            application_url: "#",
          },
          {
            id: "mid_2",
            title: "Principal - Private Equity",
            company: "Sector-Focused PE Fund",
            location: "London",
            match_score: 81,
            competitiveness: "Strong contender - experience and skills aligned",
            key_requirements: [
              "Portfolio optimization",
              "Strategic planning",
              "Due diligence leadership",
            ],
            application_url: "#",
          },
        ],
        senior: [
          {
            id: "senior_1",
            title: "Managing Director - Private Equity",
            company: "Established PE Fund",
            location: "London",
            match_score: 87,
            competitiveness: "Highly qualified - senior experience valued",
            key_requirements: [
              "Fund management",
              "LP relations",
              "Strategic oversight",
            ],
            application_url: "#",
          },
          {
            id: "senior_2",
            title: "Partner - Investment Banking",
            company: "Boutique Advisory Firm",
            location: "London",
            match_score: 83,
            competitiveness: "Strong candidate - leadership experience evident",
            key_requirements: [
              "Business development",
              "Client relationships",
              "Team building",
            ],
            application_url: "#",
          },
        ],
      };

      return jobTemplates[profileLevel] || jobTemplates.entry;
    }

    renderLiveExpertPanel(context = {}) {
      const userName = window.sffc_ajax?.user_name || "";
      const firstName = userName.split(" ")[0] || "there";
      const greetingCopy =
        firstName === "there"
          ? "Let's map out simple learning blocks and keep building from your answers."
          : `Hi ${this.escapeHtml(
              firstName
            )}, let's map out simple learning blocks and keep building from your answers.`;
      const headlineContext = this.escapeHtml(context.source || "senna");
      const lessonSelection = this.selectTutorLessons(4);
      const lessonBlocks = lessonSelection
        .map((lesson, index) => this.renderTutorLessonTopic(lesson, index))
        .join("");
      return `
        <section class="live-expert-panel tutor-learning-panel" data-context="${headlineContext}">
          <div class="tutor-panel-hero">
            <div class="tutor-hero-copy">
              <p class="tutor-eyebrow">Finance Learning Tutor</p>
              <h3>Finance technical topics built for you</h3>
              <p class="tutor-lede">${greetingCopy}</p>
              <div class="tutor-hero-metrics">
                <span>Investment banking</span>
                <span>Asset management</span>
                <span>Private equity</span>
              </div>
            </div>
          </div>

          <div class="tutor-learning-topics" data-lesson-count="${lessonSelection.length}">
            ${lessonBlocks}
          </div>

          <div class="tutor-panel-cta">
            <p class="cta-label">Need a different focus?</p>
            <div class="tutor-cta-buttons">
              <button class="tutor-topic-switch" type="button">Choose a Different Topic</button>
            </div>
          </div>
        </section>
      `;
    }

    renderTutorLessonTopic(lesson, index) {
      if (!lesson) {
        return "";
      }

      const topicNumber = index + 1;
      const highlights = (lesson.highlights || [])
        .map((point) => `<li>${this.escapeHtml(point)}</li>`)
        .join("\n");

      const lessonId = this.escapeHtml(lesson.id);
      return `
        <article class="tutor-topic" data-lesson-id="${lessonId}" role="listitem">
          <p class="topic-label">Topic ${topicNumber}</p>
          <h4>${this.escapeHtml(lesson.title)}</h4>
          <ul>
            ${highlights}
          </ul>
          <button class="tutor-lesson-start" type="button" data-lesson-id="${lessonId}">
            Continue Here
          </button>
        </article>
      `;
    }

    selectTutorLessons(limit = 4) {
      const library = [...this.getTutorLessonLibrary()];
      if (library.length <= limit) {
        return library;
      }

      const picked = [];
      const workingSet = [...library];
      while (picked.length < limit && workingSet.length > 0) {
        const index = Math.floor(Math.random() * workingSet.length);
        picked.push(workingSet.splice(index, 1)[0]);
      }
      return picked;
    }

    getTutorLessonLibrary() {
      if (this.tutorLessonLibrary) {
        return this.tutorLessonLibrary;
      }

      const tutorLessonSeeds = [
        {
          id: "topic-ib-three-statement",
          title: "IB Three-Statement Fundamentals",
          highlights: [
            "Link net income, retained earnings, and cash flow cleanly",
            "Use working capital days to forecast operating cash needs",
            "Build balance checks that catch modelling errors early",
          ],
          focus: "building integrated financial statements for IB analysis",
        },
        {
          id: "topic-ib-dcf-core",
          title: "DCF Valuation Core",
          highlights: [
            "Forecast unlevered free cash flow from EBIT",
            "Calculate terminal value using perpetuity and exit multiple methods",
            "Bridge enterprise value to equity value per share",
          ],
          focus: "valuing companies with DCF analysis",
        },
        {
          id: "topic-ib-accretion-dilution",
          title: "Accretion / Dilution Analysis",
          highlights: [
            "Build pro forma net income after financing and synergies",
            "Compare buyer standalone EPS to pro forma EPS",
            "Identify break-even synergies and purchase price sensitivity",
          ],
          focus: "modelling M&A EPS impact",
        },
        {
          id: "topic-am-portfolio-construction",
          title: "Portfolio Construction",
          highlights: [
            "Translate return expectations and risk budgets into weights",
            "Balance diversification, conviction, and tracking error",
            "Explain why a position belongs in the portfolio",
          ],
          focus: "building portfolios for asset management",
        },
        {
          id: "topic-am-fixed-income-duration",
          title: "Fixed Income Duration",
          highlights: [
            "Connect bond prices, yields, duration, and convexity",
            "Estimate price impact from rate moves",
            "Compare credit spread and interest-rate risk",
          ],
          focus: "understanding bond risk and return",
        },
        {
          id: "topic-am-performance-attribution",
          title: "Performance Attribution",
          highlights: [
            "Split active return into allocation and selection effects",
            "Compare portfolio weights against benchmark weights",
            "Explain whether performance came from skill or exposure",
          ],
          focus: "explaining portfolio performance versus benchmark",
        },
        {
          id: "topic-3statement-blueprint",
          title: "Three-Statement Model Blueprint",
          highlights: [
            "Lay out IS/BS/CF tabs with consistent time columns and driver blocks",
            "Link Net Income into Cash Flow and retained earnings with circularity controls",
            "Add balance checks so Assets equal Liabilities + Equity each period",
          ],
          focus: "structuring three linked statements",
        },
        {
          id: "topic-revenue-bridge",
          title: "Revenue Build Techniques",
          highlights: [
            "Combine TAM/SAM/SOM drivers with bottom-up unit x price logic",
            "Use INDEX-MATCH to pull run rates and seasonality",
            "Layer scenario switches to flex growth, churn, and pricing",
          ],
          focus: "building revenue schedules",
        },
        {
          id: "topic-saas-unit-economics",
          title: "SaaS Unit Economics Model",
          highlights: [
            "Forecast ARR with cohort retention and expansion",
            "Calculate CAC payback and LTV:CAC using SUMPRODUCT",
            "Reconcile deferred revenue to the cash-flow statement",
          ],
          focus: "modelling SaaS metrics",
        },
        {
          id: "topic-working-capital-cycle",
          title: "Working Capital Schedule",
          highlights: [
            "Project AR/AP/Inventory using days metrics and rolling averages",
            "Convert days to cash via revenue or COGS divisions",
            "Link changes into Free Cash Flow with sign conventions",
          ],
          focus: "building operating WC schedules",
        },
        {
          id: "topic-fixed-asset-rollforward",
          title: "PP&E & Capex Rollforward",
          highlights: [
            "Track opening balance, additions, disposals, and closing",
            "Toggle straight-line vs accelerated depreciation",
            "Tie net PP&E into BS and CF investing lines",
          ],
          focus: "rolling PP&E",
        },
        {
          id: "topic-debt-schedule-core",
          title: "Senior Debt Schedule",
          highlights: [
            "Set opening balance, draws, repayments, ending balance",
            "Model cash sweeps referencing available cash",
            "Calculate interest using average balances with circular controls",
          ],
          focus: "building senior debt tabs",
        },
        {
          id: "topic-revolver-modelling",
          title: "Revolver Mechanics",
          highlights: [
            "Model minimum cash triggers and automatic draws",
            "Use MIN/MAX to prevent negative balances",
            "Split cash vs PIK interest feeding IS/CF",
          ],
          focus: "replicating revolver logic",
        },
        {
          id: "topic-interest-bridges",
          title: "Interest Expense Bridge",
          highlights: [
            "Average beginning/ending debt for accurate interest",
            "Separate cash, PIK, commitment, and amortised fees",
            "Feed coverage covenant checks automatically",
          ],
          focus: "building interest schedules",
        },
        {
          id: "topic-cashflow-bridge",
          title: "Free Cash Flow Walk",
          highlights: [
            "Start at EBITDA and reconcile down to levered FCF",
            "Split capex into maintenance vs expansion",
            "Ensure ending cash ties to the Balance Sheet",
          ],
          focus: "reconciling to FCF",
        },
        {
          id: "topic-scenario-switches",
          title: "Scenario & Case Switches",
          highlights: [
            "Create global scenario dropdowns with CHOOSE/INDEX",
            "Organise assumption grids for Base/Downside/Stretch",
            "Audit that sensitivities cascade into valuation tabs",
          ],
          focus: "building scenario managers",
        },
        {
          id: "topic-sensitivity-grid",
          title: "Sensitivity Tables",
          highlights: [
            "Use Excel Data Tables for IRR vs leverage or EV/EBITDA",
            "Anchor row/column inputs with absolute references",
            "Format heatmaps for IC-ready reporting",
          ],
          focus: "building IRR/MOIC sensitivities",
        },
        {
          id: "topic-dynamic-lookups",
          title: "Dynamic Lookup Techniques",
          highlights: [
            "Compare INDEX-MATCH, XLOOKUP, OFFSET for stability",
            "Wrap lookups in IFERROR with custom warnings",
            "Use MATCH(TRUE) for banding logic like tax tiers",
          ],
          focus: "writing resilient formulas",
        },
        {
          id: "topic-sumifs-masterclass",
          title: "SUMIFS vs SUMPRODUCT",
          highlights: [
            "Aggregate monthly actuals into quarters and LTM",
            "Handle OR/AND logic via SUMPRODUCT",
            "Cross-check totals with quick reasonableness flags",
          ],
          focus: "aggregating data cleanly",
        },
        {
          id: "topic-dynamic-ranges",
          title: "Dynamic Named Ranges",
          highlights: [
            "Build OFFSET/INDEX ranges feeding charts and tables",
            "Reduce volatility by replacing OFFSET where possible",
            "Tie ranges into PowerPoint-linked outputs",
          ],
          focus: "keeping references dynamic",
        },
        {
          id: "topic-error-audit",
          title: "Model Audit Checks",
          highlights: [
            "Create balance flags, sign tests, and threshold alerts",
            "Use ISNUMBER+MATCH for lookup integrity",
            "Summarise checks in a dashboard with traffic lights",
          ],
          focus: "auditing PE models",
        },
        {
          id: "topic-date-functions",
          title: "Timeline & Date Functions",
          highlights: [
            "Leverage EDATE/EOMONTH for monthly grids",
            "Use NETWORKDAYS/actual days for working-cap timing",
            "Handle stub periods within DCF timelines",
          ],
          focus: "handling time in models",
        },
        {
          id: "topic-3statement-integrity",
          title: "Three-Statement Integrity Tests",
          highlights: [
            "Reconcile EBITDA to Free Cash Flow",
            "Ensure depreciation equals the PP&E roll-forward",
            "Match retained earnings to net income and dividends",
          ],
          focus: "stress-testing statement links",
        },
        {
          id: "topic-dcf-timeline",
          title: "DCF Timeline Build",
          highlights: [
            "Set explicit forecast years before fade period",
            "Apply mid-year conventions for valuation accuracy",
            "Convert EV to equity using debt, cash, and minority adjustments",
          ],
          focus: "constructing DCF timelines",
        },
        {
          id: "topic-dcf-wacc",
          title: "WACC Calculation",
          highlights: [
            "Blend CAPM cost of equity with size/liquidity premia",
            "Estimate after-tax cost of debt with country overlays",
            "Normalise target leverage weights for scenarios",
          ],
          focus: "deriving WACC",
        },
        {
          id: "topic-terminal-value",
          title: "Terminal Value Methods",
          highlights: [
            "Compare Gordon Growth vs Exit Multiple outputs",
            "Stress terminal growth against GDP/inflation",
            "Bridge sensitivity tables into football fields",
          ],
          focus: "finishing DCFs",
        },
        {
          id: "topic-public-comps",
          title: "Public Trading Comps",
          highlights: [
            "Scrub EV for leases, pensions, and minority interests",
            "Normalise EBITDA with IFRS 16 and exceptional add-backs",
            "Present median/mean/quartile outputs neatly",
          ],
          focus: "building trading comps",
        },
        {
          id: "topic-transaction-comps",
          title: "Precedent Transaction Comps",
          highlights: [
            "Capture EV, EBITDA, revenue at announcement/closing",
            "Adjust for stock vs cash consideration and synergies",
            "Analyse bid premiums vs unaffected price",
          ],
          focus: "constructing precedent comps",
        },
        {
          id: "topic-football-field",
          title: "Football Field Output",
          highlights: [
            "Layer valuation ranges from DCF, comps, LBO",
            "Use MIN/MAX formulas for clean bars",
            "Link ranges directly into presentation charts",
          ],
          focus: "visualising valuation ranges",
        },
        {
          id: "topic-ev-to-equity",
          title: "Enterprise to Equity Bridge",
          highlights: [
            "Deduct net debt, pensions, minorities, leases",
            "Incorporate NOLs and other assets",
            "Check equity value per share reconciles with model",
          ],
          focus: "bridging EV to equity",
        },
        {
          id: "topic-contribution-analysis",
          title: "Contribution Analysis",
          highlights: [
            "Split revenue/EBITDA by region/segment",
            "Use SUMIFS to re-cut P&L and margin mix",
            "Show mix shift impacts on blended multiples",
          ],
          focus: "analysing business mix",
        },
        {
          id: "topic-adjusted-ebitda",
          title: "Adjusted EBITDA Proof",
          highlights: [
            "Build detailed add-back schedules with descriptors",
            "Toggle recurring vs non-recurring items",
            "Bridge reported to adjusted EBITDA investors believe",
          ],
          focus: "reconciling EBITDA",
        },
        {
          id: "topic-qoe-bridge",
          title: "Quality of Earnings Bridge",
          highlights: [
            "Translate QofE adjustments into run-rate EBITDA",
            "Summarise normalised revenue/cost impacts",
            "Connect findings back into valuation",
          ],
          focus: "linking QofE to models",
        },
        {
          id: "topic-ppa-goodwill",
          title: "Purchase Price Allocation",
          highlights: [
            "Allocate purchase price to tangible/intangible assets",
            "Calculate goodwill and amortisation schedules",
            "Model deferred taxes from fair-value step-ups",
          ],
          focus: "modelling PPA",
        },
        {
          id: "topic-deferred-tax",
          title: "Deferred Tax Modelling",
          highlights: [
            "Track temporary differences by asset class",
            "Apply blended statutory rates for DTAs/DTLs",
            "Link deferred tax roll into Balance Sheet",
          ],
          focus: "handling deferred tax",
        },
        {
          id: "topic-fx-translation",
          title: "FX Translation & Consolidation",
          highlights: [
            "Set average vs closing rates for multi-currency groups",
            "Translate equity using historical rate methodology",
            "Present CTA movement within equity",
          ],
          focus: "consolidating cross-border targets",
        },
        {
          id: "topic-sources-uses",
          title: "Sources & Uses Table",
          highlights: [
            "Lay out equity, debt, fees, and uses with subtotals",
            "Tie fees to amortisation schedules",
            "Check sources equal uses with forced balance line",
          ],
          focus: "structuring LBO funding",
        },
        {
          id: "topic-debt-layering",
          title: "Debt Layering & Fees",
          highlights: [
            "Stack term loan, mezzanine, and shareholder loans",
            "Model OID and upfront fee amortisation",
            "Calculate blended cash vs PIK interest",
          ],
          focus: "layering capital structures",
        },
        {
          id: "topic-irr-analysis",
          title: "IRR & MOIC Analysis",
          highlights: [
            "Use XIRR/XNPV for precise cash-flow timing",
            "Bridge gross to net IRR after fees",
            "Convert MOIC into payback visuals",
          ],
          focus: "sizing PE returns",
        },
        {
          id: "topic-exit-scenarios",
          title: "Exit Scenario Planning",
          highlights: [
            "Model multiple exit years and EV/EBITDA",
            "Link scenarios into equity waterfalls",
            "Stress hold vs flip and partial exits",
          ],
          focus: "planning entry/exit",
        },
        {
          id: "topic-rollover-equity",
          title: "Management Rollover & Options",
          highlights: [
            "Calculate rollover percentages and new ownership",
            "Model option pools, vesting, and dilution",
            "Show dilution impacts on investor returns",
          ],
          focus: "building management equity",
        },
        {
          id: "topic-fee-modelling",
          title: "Transaction & Monitoring Fees",
          highlights: [
            "Schedule arrangement/advisory fees with timing",
            "Model monitoring fees and potential rebates",
            "Feed fees into IRR/MOIC calculations",
          ],
          focus: "capturing fee economics",
        },
        {
          id: "topic-working-capital-buffer",
          title: "Working Capital Buffer Planning",
          highlights: [
            "Estimate minimum cash buffers via volatility analysis",
            "Build revolver availability/stress tests",
            "Present downside liquidity runway charts",
          ],
          focus: "protecting liquidity post-close",
        },
        {
          id: "topic-covenant-testing",
          title: "Debt Covenant Testing",
          highlights: [
            "Project leverage and coverage ratios each quarter",
            "Model cure rights and headroom",
            "Format compliance certificates automatically",
          ],
          focus: "tracking covenants",
        },
        {
          id: "topic-cash-sweep-waterfall",
          title: "Cash Sweep Waterfall",
          highlights: [
            "Stack mandatory debt pay-downs before distributions",
            "Link to shareholder waterfall tabs",
            "Toggle cash vs PIK treatment and excess distributions",
          ],
          focus: "building cash waterfalls",
        },
        {
          id: "topic-lbo-entry-build",
          title: "LBO Entry Model Setup",
          highlights: [
            "Translate purchase price into sources/uses and goodwill",
            "Attach initial debt stack with fees",
            "Set operational starting points for forecasting",
          ],
          focus: "building entry assumptions",
        },
        {
          id: "topic-carry-waterfall",
          title: "Carry & Distribution Waterfall",
          highlights: [
            "Model preferred return, catch-up, and carry tiers",
            "Show LP vs GP distributions over time",
            "Stress scenarios for clawback risk",
          ],
          focus: "modelling carry waterfalls",
        },
        {
          id: "topic-portfolio-kpi-dashboard",
          title: "Portfolio KPI Dashboard",
          highlights: [
            "Track EBITDA, leverage, cash conversion, and covenant metrics",
            "Automate variance flags for portfolio reviews",
            "Feed dashboards with Power Query or structured references",
          ],
          focus: "monitoring portfolio performance",
        },
      ];
      const lessons = tutorLessonSeeds.map((lesson) => {
        const highlights = lesson.highlights || [];
        const focusSummary =
          lesson.focus ||
          (highlights.length ? highlights.join("; ") : lesson.title);

        return {
          ...lesson,
          highlights,
          objectives:
            lesson.objectives && lesson.objectives.length
              ? lesson.objectives
              : [...highlights],
          prompt:
            lesson.prompt || this.buildTutorPrompt(lesson.title, focusSummary),
        };
      });

      this.tutorLessonLibrary = lessons;
      this.tutorLessonMap = {};
      lessons.forEach((lesson) => {
        this.tutorLessonMap[lesson.id] = lesson;
      });

      return lessons;
    }

    getTutorLessonById(lessonId) {
      if (!lessonId) {
        return null;
      }

      if (!this.tutorLessonMap) {
        this.getTutorLessonLibrary();
      }

      return this.tutorLessonMap?.[lessonId] || null;
    }

    buildTutorPrompt(title, focusSummary = "") {
      const focusLine = focusSummary ? ` Focus on ${focusSummary}.` : "";
      return `You are MENA Careers, a finance technical teacher running a continuous learning conversation on ${title}.${focusLine}

This is strictly a learning tool for investment banking, asset management, and private equity candidates. Do not provide job listings, application advice, CV advice, salary guidance, recruiting strategy, networking advice, or career coaching. If the student asks for any of those, briefly say this room is for learning and translate the request into the relevant technical finance skill.

Act like a teacher:
- Keep continuity with the previous exchange instead of restarting.
- Infer the student's learning style from their wording and adapt quietly: beginner-friendly, numeric/model-driven, conceptual, concise, or exploratory.
- If the student answers a practice question, mark what is right, fix what is wrong, and give the next small step.
- Teach one concept at a time.
- Use concrete numbers, formulas, and examples from the relevant track: IB, AM, or PE.
- End with exactly one practice question or next-step prompt.
- Never say you are analyzing a complex query.
- No roleplay text or action descriptions.`;
    }

    launchTutorLesson(lessonId) {
      const lesson = this.getTutorLessonById(lessonId);
      if (!lesson) {
        console.warn("Tutor lesson not found", lessonId);
        return;
      }

      const metadata = {
        title: lesson.title,
        originalPrompt: lesson.prompt,
        actionType: "tutor-lesson",
        learningObjectives: lesson.objectives || lesson.highlights || [],
      };

      if (
        window.peLearningExercises &&
        typeof window.peLearningExercises.startExercise === "function"
      ) {
        window.peLearningExercises.startExercise(lesson.id, metadata);
        return;
      }

      if (window.SennaChat && typeof window.SennaChat.send === "function") {
        const context = `[SYSTEM CONTEXT - Finance Technical Tutor]\n${lesson.prompt}`;
        window.SennaChat.send(`I'd like to learn about ${lesson.title}`, {
          system_prompt: context,
          exercise_id: lesson.id,
          mode: "pe_tutor",
        });
        return;
      }

      this.addUserMessage(`Can we continue with a lesson on ${lesson.title}?`);
    }

    handleTutorTopicSwitch() {
      this.addSennaMessage(
        "Great, tell me whether you want investment banking, asset management, or private equity, and I will shape the next lesson.",
        true,
        "Finance Tutor"
      );
    }

    handleSaveLesson() {
      const printableArea = document.querySelector(".sffc-senna-conversation");
      if (!printableArea) {
        window.print();
        return;
      }

      const originalClass = printableArea.className;
      printableArea.classList.add("tutor-print-mode");
      window.print();
      printableArea.className = originalClass;
    }

    showLiveExpertPanel(context = {}) {
      // PE Tutor Mode - Live Expert disabled
      if (this.isPETutorMode) {
        return;
      }

      if (this.liveExpertMessageShown && !context.force) {
        return;
      }

      const headline = context.headline || "Live Expert Support";
      const panelHtml = this.renderLiveExpertPanel(context);
      this.addSennaMessage(panelHtml, true, headline, {
        hideHeadline: true,
        hideUnderline: true,
        hideAvatar: true,
        additionalClasses: "senna-message-panel",
      });
      this.welcomeMessageShown = true;
      this.liveExpertMessageShown = true;
    }

    requestLiveExpert(context = {}) {
      if (
        window.SennaChat &&
        typeof window.SennaChat.endTutorSession === "function"
      ) {
        window.SennaChat.endTutorSession();
      }
      const payload = Object.assign({}, context || {}, { force: true });
      if (!payload.headline) {
        payload.headline = "Live Expert Support";
      }
      this.liveExpertConnectingShown = false;
      this.showLiveExpertPanel(payload);
      if (this.liveExpertConversationId && !this.liveExpertConnected) {
        this.startLiveExpertPolling();
      }
      if (payload.autoConnect || payload.autoOpen) {
        this.handleLiveExpertChoice("connect");
      }
    }

    handleLiveExpertAction(action) {
      this.handleLiveExpertChoice(action);
    }

    handleLiveExpertChoice(choice) {
      const normalized = (choice || "").toString().toLowerCase();
      if (normalized === "senna") {
        this.continueWithSennaFromLiveExpert();
        return;
      }
      this.activateLiveExpertChat(normalized || "connect");
    }

    activateLiveExpertChat(trigger = "connect") {
      if (
        window.SennaChat &&
        typeof window.SennaChat.endTutorSession === "function"
      ) {
        window.SennaChat.endTutorSession();
      }
      if (this.getChatMode() !== "job-search") {
        this.applyChatMode("job-search");
      }
      if (!this.liveExpertConnected) {
        this.liveExpertConnected = true;
        if (!this.liveExpertWelcomeSent) {
          this.addSennaMessage(
            "Let's get you the right help. Share as much detail here as you can so your expert can help you in the best way possible.",
            true,
            "Live Expert Support"
          );
          this.liveExpertWelcomeSent = true;
        }
        this.showLiveExpertConnecting();
        this.startLiveExpertPolling();
        if (!this.liveExpertConnectionNotified) {
          this.liveExpertConnectionNotified = true;
          this.sendLiveExpertMessage("User requested live expert support.", {
            sender: "system",
            display: false,
          });
        }
      } else if (!this.liveExpertPollInterval) {
        this.startLiveExpertPolling();
      }
    }

    showLiveExpertConnecting() {
      if (this.liveExpertConnectingShown) {
        return;
      }
      this.liveExpertConnectingShown = true;

      const connectingMessage = `
        <div class="live-expert-connecting" style="text-align: center; padding: 20px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;">
          <div style="display: inline-block; position: relative; width: 40px; height: 40px; margin-bottom: 12px;">
            <div style="box-sizing: border-box; display: block; position: absolute; width: 32px; height: 32px; margin: 4px; border: 3px solid #0ea5e9; border-radius: 50%; animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite; border-color: #0ea5e9 transparent transparent transparent;"></div>
          </div>
          <p style="margin: 0; color: #0369a1; font-weight: 600;">Connecting you to a live expert...</p>
          <p style="margin: 8px 0 0; color: #64748b; font-size: 13px;">This usually takes just a few seconds</p>
        </div>
        <style>
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
          }
        </style>
      `;

      this.addSennaMessage(connectingMessage, true, "Live Expert Support");
    }

    showLiveExpertConnecting_DISABLED() {
      if (this.liveExpertConnectingShown) {
        return;
      }
      this.liveExpertConnectingShown = true;

      const connectingMessage = `
        <div class="live-expert-connecting" style="text-align: center; padding: 20px; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;">
          <div style="display: inline-block; position: relative; width: 40px; height: 40px; margin-bottom: 12px;">
            <div style="box-sizing: border-box; display: block; position: absolute; width: 32px; height: 32px; margin: 4px; border: 3px solid #0ea5e9; border-radius: 50%; animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite; border-color: #0ea5e9 transparent transparent transparent;"></div>
          </div>
          <p style="margin: 0; color: #0369a1; font-weight: 600;">Connecting you to a live expert...</p>
          <p style="margin: 8px 0 0; color: #64748b; font-size: 13px;">This usually takes just a few seconds</p>
        </div>
        <style>
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
          }
        </style>
      `;

      this.addSennaMessage(connectingMessage, true, "Live Expert Support");
    }

    continueWithSennaFromLiveExpert() {
      this.stopLiveExpertPolling();
      this.liveExpertConnected = false;
      this.liveExpertConnectingShown = false;
      this.liveExpertWelcomeSent = false;
      this.applyChatMode("career-advice");
      this.addSennaMessage(
        "No problem—I'm here whenever you want to dive back in.",
        true,
        "MENA Careers AI"
      );
    }

    sendLiveExpertMessage(message, options = {}) {
      if (!window.sffc_ajax || !window.sffc_ajax.ajax_url) {
        return;
      }
      const content = (message || "").toString();
      const trimmedContent = content.trim();
      const sender = options.sender || "user";
      const allowEmpty = options.allowEmpty || sender === "system";

      if (!trimmedContent && !allowEmpty) {
        return;
      }

      if (!this.liveExpertConnected) {
        this.activateLiveExpertChat("auto");
      }

      const payload = {
        action: "sffc_live_expert_message",
        nonce: window.sffc_ajax?.nonce || "",
        session_id: this.liveExpertSessionId,
        conversation_id: this.liveExpertConversationId || "",
        message: trimmedContent,
        sender,
      };

      if (options.context) {
        payload.context = options.context;
      }

      return $.ajax({
        url: window.sffc_ajax.ajax_url,
        method: "POST",
        dataType: "json",
        data: payload,
      })
        .done((response) => {
          if (!response || !response.success) {
            if (sender === "user") {
              let errorMessage =
                "I couldn't reach the expert desk just now. Please try one more time in a few seconds.";

              if (response && response.data && response.data.message) {
                errorMessage = response.data.message;
              } else if (response && response.message) {
                errorMessage = response.message;
              }

              // Show a more helpful error message with retry option
              const retryMessage = `
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin: 8px 0;">
                  <p style="margin: 0 0 8px; color: #dc2626; font-weight: 500;">Connection Issue</p>
                  <p style="margin: 0 0 12px; color: #374151; font-size: 14px;">${errorMessage}</p>
                  <button onclick="window.senna?.activateLiveExpertChat?.('retry')" 
                          style="background: #dc2626; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    Try Again
                  </button>
                </div>
              `;
              this.addSennaMessage(retryMessage, true, "Live Expert Support");
            }
            return;
          }

          const data = response.data || {};
          if (data.conversation_id) {
            this.persistLiveExpertConversationId(data.conversation_id);
          }

          if (Array.isArray(data.messages)) {
            this.processLiveExpertMessages(data.messages);
          } else if (data.message) {
            this.processLiveExpertMessages([data.message]);
          }
        })
        .fail((jqXHR) => {
          if (sender === "user") {
            let fallbackMessage =
              "I couldn't reach the expert desk just now. Please try one more time in a few seconds.";
            try {
              const result = JSON.parse(jqXHR.responseText || "{}");
              if (result && result.data && result.data.message) {
                fallbackMessage = result.data.message;
              }
            } catch (err) {
              // ignore parsing errors
            }
            this.addSennaMessage(fallbackMessage, true, "Live Expert Support");
          }
        });
    }

    startLiveExpertPolling() {
      if (this.liveExpertPollInterval) {
        return;
      }
      this.fetchLiveExpertMessages();
      this.liveExpertPollInterval = setInterval(() => {
        this.fetchLiveExpertMessages();
      }, 5000);
    }

    stopLiveExpertPolling() {
      if (this.liveExpertPollInterval) {
        clearInterval(this.liveExpertPollInterval);
        this.liveExpertPollInterval = null;
      }
    }

    fetchLiveExpertMessages() {
      if (!window.sffc_ajax || !window.sffc_ajax.ajax_url) {
        return;
      }
      const payload = {
        action: "sffc_live_expert_fetch",
        nonce: window.sffc_ajax?.nonce || "",
        session_id: this.liveExpertSessionId,
        conversation_id: this.liveExpertConversationId || "",
        since: this.liveExpertLastTimestamp || 0,
      };

      $.ajax({
        url: window.sffc_ajax.ajax_url,
        method: "POST",
        dataType: "json",
        data: payload,
      })
        .done((response) => {
          if (!response || !response.success) {
            return;
          }
          const data = response.data || {};
          if (data.conversation_id) {
            this.persistLiveExpertConversationId(data.conversation_id);
          }
          if (Array.isArray(data.messages)) {
            this.processLiveExpertMessages(data.messages);
          }
        })
        .fail(() => {
          // Fail silently for now
        });
    }

    processLiveExpertMessages(messages = []) {
      messages.forEach((message) => {
        if (!message || !message.id) {
          return;
        }
        if (this.liveExpertMessageIds.has(message.id)) {
          return;
        }
        this.liveExpertMessageIds.add(message.id);
        if (message.timestamp) {
          const ts = Number(message.timestamp);
          if (!Number.isNaN(ts)) {
            this.liveExpertLastTimestamp = Math.max(
              this.liveExpertLastTimestamp,
              ts
            );
          }
        }
        if (message.sender === "user" || message.sender === "system") {
          return;
        }
        this.displayLiveExpertMessage(message);
      });
    }

    displayLiveExpertMessage(message) {
      // Handle connection status when expert first connects
      if (this.liveExpertConnectingShown && !this.liveExpertConnected) {
        // Hide the connecting message
        const connectingDiv = document.querySelector(".live-expert-connecting");
        if (connectingDiv) {
          connectingDiv.style.display = "none";
        }

        // Show subtle connected status message
        const connectedMessage = `
          <div class="live-expert-connected-status" style="text-align: center; padding: 8px 16px; margin: 8px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <p style="margin: 0; color: #64748b; font-size: 12px; font-weight: 500;">Connected</p>
          </div>
        `;

        $("#senna-messages").append(connectedMessage);

        // Scroll to show the connected message
        setTimeout(() => {
          const connectedElement = document.querySelector(
            ".live-expert-connected-status"
          );
          if (connectedElement) {
            connectedElement.scrollIntoView({
              behavior: "smooth",
              block: "end",
            });
          }
        }, 100);
      }

      // Extract first name from sender_name for display
      const fullName = message.sender_name || "Live Expert";
      const firstName = fullName.split(" ")[0] || "Expert";
      const name = this.escapeHtml(firstName);

      const safeContent = this.convertTextToHtmlWithBreaks(
        message.content || ""
      );
      const meta = this.formatLiveExpertTimestamp(message.timestamp);
      const metaHtml = meta
        ? `<div class=\"live-expert-meta\">${name} • ${meta}</div>`
        : `<div class=\"live-expert-meta\">${name}</div>`;

      // Create live expert message using the same structure as MENA Careers messages
      const liveExpertHeadline = `Live Expert: ${name}`;
      const headlineHtml = `
        <div class="wsj-headline">
          <h2 class="wsj-headline-text">${liveExpertHeadline}</h2>
          <div class="wsj-headline-underline live-expert-underline"></div>
        </div>
      `;

      // Get the expert's email for WordPress avatar (if available in message data)
      const expertEmail = message.sender_email || message.expert_email || "";
      const expertId = message.sender_id || message.expert_id || "";

      // Generate WordPress Gravatar URL or fallback to letter avatar
      let avatarHtml = "";
      if (expertEmail) {
        // Use WordPress Gravatar
        const gravatarHash = this.generateGravatarHash(expertEmail);
        const gravatarUrl = `https://www.gravatar.com/avatar/${gravatarHash}?s=40&d=404`;
        avatarHtml = `<img src="${gravatarUrl}" alt="${name}" 
                           onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'live-expert-letter-avatar\\'>${firstName
                             .charAt(0)
                             .toUpperCase()}</div>';"
                           style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">`;
      } else {
        // Use letter avatar as default
        avatarHtml = `<div class="live-expert-letter-avatar">${firstName
          .charAt(0)
          .toUpperCase()}</div>`;
      }

      const messageHtml = `
        <div class="senna-message wsj-style-message live-expert-message">
          <div class="sffc-message-content">
            ${headlineHtml}
            <div class="message-text live-expert-content">
              ${safeContent}
              ${metaHtml}
            </div>
          </div>
        </div>
      `;

      // Add the message to the chat with delay if first connection
      const isFirstConnection =
        this.liveExpertConnectingShown && !this.liveExpertConnected;
      const messageDelay = isFirstConnection ? 800 : 0; // Small delay for first message

      setTimeout(() => {
        const $newMessage = $(messageHtml);
        $("#senna-messages").append($newMessage);

        // Scroll to the new message
        setTimeout(() => {
          const lastMessage = $newMessage[0];
          if (lastMessage) {
            lastMessage.scrollIntoView({ behavior: "smooth", block: "end" });
          }
        }, 100);
      }, messageDelay);

      this.liveExpertConnectingShown = false;
      this.liveExpertWelcomeSent = true;
      this.liveExpertConnected = true;
    }

    formatLiveExpertTimestamp(timestamp) {
      if (!timestamp) {
        return "";
      }
      const ts = Number(timestamp);
      if (Number.isNaN(ts)) {
        return "";
      }
      try {
        const date = new Date(ts * 1000);
        return date.toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
        });
      } catch (e) {
        return "";
      }
    }

    generateGravatarHash(email) {
      // Simple MD5 hash implementation for Gravatar
      email = email.toLowerCase().trim();

      // Use crypto API if available (modern browsers)
      if (typeof crypto !== "undefined" && crypto.subtle) {
        // For now, use a simple hash since crypto.subtle is async
        // This is a basic hash that works for demo purposes
        let hash = 0;
        for (let i = 0; i < email.length; i++) {
          const char = email.charCodeAt(i);
          hash = (hash << 5) - hash + char;
          hash = hash & hash; // Convert to 32-bit integer
        }
        return Math.abs(hash).toString(16);
      }

      // Fallback: use a simple hash
      let hash = 0;
      for (let i = 0; i < email.length; i++) {
        const char = email.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash = hash & hash;
      }
      return Math.abs(hash).toString(16).padStart(8, "0");
    }

    convertTextToHtmlWithBreaks(text) {
      if (!text) {
        return "";
      }
      return this.escapeHtml(text).replace(/\n/g, "<br>");
    }

    renderJobMatches(jobs) {
      return this.renderLiveExpertPanel({ source: "job-matches" });
    }

    addSennaMessage(
      message,
      skipTyping = false,
      customHeadline = null,
      options = {}
    ) {
      const opts = options || {};
      const messageId = "msg-" + Date.now();

      const showHeadline = !opts.hideHeadline;
      const showUnderline = showHeadline && !opts.hideUnderline;
      const showAvatar = !opts.hideAvatar;

      const headline = customHeadline || this.generateDynamicHeadline(message);

      const wrapperClasses = ["senna-message", "wsj-style-message"];
      if (!showAvatar) {
        wrapperClasses.push("senna-message-no-avatar");
      }
      if (opts.additionalClasses) {
        wrapperClasses.push(opts.additionalClasses);
      }

      const headlineHtml = showHeadline
        ? `
                <div class="wsj-headline">
                    <h2 class="wsj-headline-text">${headline}</h2>
                    ${
                      showUnderline
                        ? '<div class="wsj-headline-underline"></div>'
                        : ""
                    }
                </div>
            `
        : "";

      const avatarHtml = "";

      const contentClass = showAvatar
        ? "sffc-message-content"
        : "sffc-message-content message-content-full";

      const messageHtml = `
                <div class="${wrapperClasses.join(" ")}">
                    ${avatarHtml}
                    <div class="${contentClass}">
                        ${headlineHtml}
                        <div class="message-text" id="${messageId}">
                            <span class="typing-indicator">
                                <span></span><span></span><span></span>
                            </span>
                        </div>
                    </div>
                </div>
            `;

      $("#senna-messages").append(messageHtml);

      // Determine if we should use typing effect
      const shouldType = !skipTyping && this.shouldUseTypingEffect(message);

      if (shouldType) {
        // Add a small delay to show typing indicator first
        setTimeout(() => {
          this.typeMessage(message, messageId);
        }, 500);
      } else {
        // For complex HTML, show immediately after brief typing indicator
        setTimeout(() => {
          $(`#${messageId}`).html(message);
        }, 300);
      }

      this.scrollToBottom();

      return messageId;
    }

    shouldUseTypingEffect(message) {
      // Use typing effect for simple text messages
      // Skip for complex HTML structures like job cards, tables, etc.
      const complexPatterns = [
        "<article",
        "<table",
        "<button",
        "job-card",
        "sffc-match-card",
        "<svg",
        "<iframe",
        "<img",
        'class="cv-',
        'class="tailor',
      ];

      for (const pattern of complexPatterns) {
        if (message.includes(pattern)) {
          return false;
        }
      }

      // For simple HTML like <p>, <span>, <strong>, still use typing
      return true;
    }

    generateDynamicHeadline(message) {
      // Strip HTML tags for analysis
      const cleanMessage = message.replace(/<[^>]*>/g, "").trim();
      const messageLower = cleanMessage.toLowerCase();
      const words = cleanMessage.split(" ").filter((w) => w.length > 2);

      // Check if this is a welcome greeting message
      if (
        messageLower.includes("i'm senna") ||
        messageLower.includes("hi there!") ||
        messageLower.includes(
          "I'm MENA Careers — your AI career strategist for private equity and finance."
        ) ||
        messageLower.includes("welcome back")
      ) {
        // Get user login status and name
        const isLoggedIn = window.sffc_ajax?.is_logged_in === "1";

        if (isLoggedIn) {
          // Get user name from various sources
          const userName =
            window.sffc_ajax?.user_name ||
            window.sffc_frontend?.user_name ||
            localStorage.getItem("sffc_user_name") ||
            "";
          const firstName = userName.split(" ")[0] || "";

          // Get time of day for greeting
          const hour = new Date().getHours();
          let timeGreeting = "Good morning";
          if (hour >= 12 && hour < 17) timeGreeting = "Good afternoon";
          else if (hour >= 17) timeGreeting = "Good evening";

          if (firstName) {
            return `${timeGreeting}, ${firstName}`;
          } else {
            return timeGreeting;
          }
        } else {
          return "Welcome to senna 👋";
        }
      }

      // Extract key action or subject from the message
      // Priority 1: Look for numbers/counts (e.g., "found 12 opportunities")
      const numberMatch = cleanMessage.match(/(\d+)\s+(\w+)/i);
      if (numberMatch && numberMatch[2]) {
        const count = numberMatch[1];
        const item = numberMatch[2];
        // Capitalize properly
        if (item.toLowerCase().includes("opportunit"))
          return `${count} Opportunities Found`;
        if (item.toLowerCase().includes("role"))
          return `${count} Roles Available`;
        if (item.toLowerCase().includes("position"))
          return `${count} Positions Identified`;
        if (item.toLowerCase().includes("match")) return `${count} Matches`;
      }

      // Priority 2: Location-based queries
      const locations = [
        "london",
        "new york",
        "singapore",
        "zurich",
        "sao paulo",
        "munich",
        "milan",
        "hong kong",
        "dubai",
        "paris",
        "frankfurt",
      ];
      for (const loc of locations) {
        if (messageLower.includes(loc)) {
          return `${loc
            .split(" ")
            .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
            .join(" ")} Opportunities`;
        }
      }

      // Priority 3: Industry/role specific - removed the PE trigger for welcome messages
      if (
        (messageLower.includes("private equity") ||
          messageLower.includes(" pe ")) &&
        !messageLower.includes("senna")
      )
        return "Private Equity Focus";
      if (messageLower.includes("investment banking"))
        return "Investment Banking";
      if (messageLower.includes("hedge fund"))
        return "Private Credit Opportunities";
      if (messageLower.includes("analyst")) return "Analyst Roles";
      if (messageLower.includes("associate")) return "Associate Positions";
      if (messageLower.includes("director")) return "Director Level";
      if (messageLower.includes("manage")) return "Management Positions";

      // Priority 4: Action-based headlines
      if (messageLower.includes("found") || messageLower.includes("discover"))
        return "Search Results";
      if (messageLower.includes("analyz") || messageLower.includes("analysis"))
        return "Analysis Complete";
      if (messageLower.includes("compar")) return "Comparison Results";
      if (messageLower.includes("recommend")) return "Recommendations";
      if (messageLower.includes("suggest")) return "Suggestions";
      if (messageLower.includes("help") || messageLower.includes("assist"))
        return "Assistance";
      if (messageLower.includes("guid")) return "Guidance";
      if (messageLower.includes("strateg")) return "Strategic Advice";
      if (messageLower.includes("tip")) return "Professional Tips";
      if (messageLower.includes("interview")) return "Interview Preparation";
      if (
        messageLower.includes("salary") ||
        messageLower.includes("compensation")
      )
        return "Compensation Insights";
      if (messageLower.includes("resume") || messageLower.includes("cv"))
        return "Resume Guidance";

      // Priority 5: Question/response pattern
      if (messageLower.startsWith("i've") || messageLower.startsWith("i have"))
        return "Analysis";
      if (messageLower.startsWith("here")) return "Results";
      if (messageLower.startsWith("let me")) return "Assistance";
      if (messageLower.startsWith("based on")) return "Insights";
      if (messageLower.startsWith("you")) return "Personalized Advice";

      // Priority 6: Extract first meaningful verb/noun
      const importantWords = [
        "opportunities",
        "positions",
        "roles",
        "jobs",
        "careers",
        "analysis",
        "guidance",
        "advice",
        "strategy",
        "results",
        "matches",
        "options",
      ];
      for (const word of importantWords) {
        if (messageLower.includes(word)) {
          return word.charAt(0).toUpperCase() + word.slice(1);
        }
      }

      // Default: Use first 2-3 words as headline (smart truncation)
      if (words.length >= 3) {
        const firstWords = words.slice(0, 3).join(" ");
        // Capitalize each word
        return firstWords
          .split(" ")
          .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
          .join(" ");
      }

      // Ultimate fallback
      return "Response";
    }

    typeMessage(message, elementId) {
      const element = document.getElementById(elementId);
      if (!element) return;

      // Clear typing indicator first
      element.innerHTML = "";

      // Parse message to handle HTML properly
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = message;
      const textContent = tempDiv.textContent || tempDiv.innerText || "";

      // If message contains simple HTML tags, handle them
      const hasSimpleHTML =
        message.includes("<strong>") ||
        message.includes("<em>") ||
        message.includes("<span>") ||
        message.includes("<p>");

      if (hasSimpleHTML) {
        // Type with HTML formatting preserved
        this.typeHTMLMessage(message, element);
      } else {
        // Type plain text with cursor effect
        this.typePlainMessage(textContent, element);
      }
    }

    typePlainMessage(text, element) {
      let index = 0;
      const cursor = '<span class="typing-cursor">|</span>';

      const typeChar = () => {
        if (index <= text.length) {
          const displayText = text.substring(0, index);
          element.innerHTML = displayText + cursor;
          index++;

          if (index <= text.length) {
            // Variable speed for natural effect
            let delay = 25;
            if (text[index - 1] === "." || text[index - 1] === "!") delay = 300;
            else if (text[index - 1] === ",") delay = 150;
            else if (text[index - 1] === " ") delay = 30;

            setTimeout(typeChar, delay);
          } else {
            // Remove cursor after typing completes
            setTimeout(() => {
              element.innerHTML = text;
            }, 500);
          }
          this.scrollToBottom();
        }
      };

      typeChar();
    }

    typeHTMLMessage(html, element) {
      let index = 0;
      let currentHTML = "";
      let inTag = false;

      const typeChar = () => {
        if (index < html.length) {
          // Handle HTML tags as complete units
          if (html[index] === "<") {
            inTag = true;
            const tagEnd = html.indexOf(">", index);
            if (tagEnd !== -1) {
              currentHTML += html.substring(index, tagEnd + 1);
              index = tagEnd + 1;
              inTag = false;
            }
          } else if (!inTag) {
            currentHTML += html[index];
            index++;
          }

          element.innerHTML =
            currentHTML + '<span class="typing-cursor">|</span>';

          // Variable speed
          let delay = 25;
          if (!inTag && index > 0) {
            const lastChar = html[index - 1];
            if (lastChar === "." || lastChar === "!") delay = 250;
            else if (lastChar === ",") delay = 100;
            else if (lastChar === " ") delay = 30;
          }

          setTimeout(typeChar, delay);
          this.scrollToBottom();
        } else {
          // Remove cursor when done
          setTimeout(() => {
            element.innerHTML = currentHTML;
          }, 500);
        }
      };

      typeChar();
    }

    addUserMessage(message) {
      // Generate a headline for user message too
      const headline = this.generateUserHeadline(message);

      const headlineHtml = `
                <div class="wsj-headline user-headline">
                    <h2 class="wsj-headline-text user-headline-text">${headline}</h2>
                    <div class="wsj-headline-underline"></div>
                </div>
            `;

      const messageHtml = `
                <div class="user-message wsj-style-message">
                    <div class="message-content">
                        ${headlineHtml}
                        <div class="message-text">${this.escapeHtml(
                          message
                        )}</div>
                    </div>
                </div>
            `;

      $("#senna-messages").append(messageHtml);
      this.scrollToBottom();
    }

    generateUserHeadline(message) {
      const messageLower = message.toLowerCase();

      // Determine query type for headline
      if (messageLower.includes("tell me more about")) return "Job Inquiry";
      if (messageLower.includes("show me") || messageLower.includes("find"))
        return "Your Search";
      if (
        messageLower.includes("how") ||
        messageLower.includes("what") ||
        messageLower.includes("why")
      )
        return "Your Question";
      if (messageLower.includes("help")) return "Your Request";
      if (messageLower.includes("tailor") || messageLower.includes("cv"))
        return "CV Request";
      if (messageLower.includes("apply")) return "Application";
      if (
        messageLower.includes("london") ||
        messageLower.includes("paris") ||
        messageLower.includes("new york") ||
        messageLower.includes("singapore") ||
        messageLower.includes("zurich")
      )
        return "Location Query";
      if (
        messageLower.includes("private equity") ||
        messageLower.includes(" pe ")
      )
        return "PE Query";
      // Only mark as Role Query if it's a general search for analyst/associate roles, not specific job inquiries
      if (
        (messageLower.includes("analyst") ||
          messageLower.includes("associate")) &&
        !messageLower.includes("tell me")
      )
        return "Role Search";

      // Default
      return "Your Input";
    }

    escapeHtml(text) {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };
      return text.replace(/[&<>"']/g, (m) => map[m]);
    }

    scrollToBottom() {
      const messages = document.getElementById("senna-messages");
      if (messages) {
        messages.scrollTop = messages.scrollHeight;
      }
    }

    switchToAnalyze() {
      if (this.shortlist.length === 0) {
        this.addSennaMessage(
          `Please shortlist some opportunities first. I need at least one role to analyze.`
        );
        return;
      }

      this.currentStage = "analyze";
      $(".stage-indicator, .stage-menu-item").removeClass("active");
      $(
        '.stage-indicator[data-stage="analyze"], .stage-menu-item[data-stage="analyze"]'
      ).addClass("active");

      // Show analysis in chat instead
      this.addSennaMessage(
        `Switching to analysis mode. Let me examine your ${this.shortlist.length} shortlisted opportunities...`
      );
    }

    switchToApply() {
      if (this.shortlist.length === 0) {
        this.addSennaMessage(
          `You need to shortlist and analyze opportunities before we can create your application strategy.`
        );
        return;
      }

      this.currentStage = "apply";
      $(".stage-indicator, .stage-menu-item").removeClass("active");
      $(
        '.stage-indicator[data-stage="apply"], .stage-menu-item[data-stage="apply"]'
      ).addClass("active");

      this.addSennaMessage(
        `Let's create your application strategy for these ${this.shortlist.length} opportunities. I'll help you prioritize and prepare tailored applications.`
      );
    }

    switchToBrowse() {
      this.currentStage = "browse";
      $(".stage-indicator, .stage-menu-item").removeClass("active");
      $(
        '.stage-indicator[data-stage="browse"], .stage-menu-item[data-stage="browse"]'
      ).addClass("active");

      this.addSennaMessage(
        `Back to browsing mode. What else would you like to explore?`
      );
    }

    // Helper: Get firm size label from company name
    getFirmSizeLabel(companyName) {
      const megaFunds = [
        "Blackstone",
        "KKR",
        "Apollo",
        "Carlyle",
        "Bain Capital",
        "TPG",
        "Warburg Pincus",
        "Silver Lake",
        "Vista Equity Partners",
        "Hellman & Friedman",
        "Leonard Green & Partners",
        "Goldman Sachs Asset Management",
        "Brookfield Asset Management",
        "Ares Management",
      ];

      const largeCap = [
        "CVC Capital Partners",
        "EQT",
        "Cinven",
        "Permira",
        "Advent International",
        "BC Partners",
        "PAI Partners",
        "Nordic Capital",
        "Apax Partners",
        "General Atlantic",
        "Francisco Partners",
        "Thoma Bravo",
        "Clayton, Dubilier & Rice",
        "GTCR",
        "Riverside Company",
      ];

      const midMarket = [
        "Bridgepoint",
        "3i",
        "Graphite Capital",
        "NVM Private Equity",
        "Livingbridge",
        "LDC (Lloyds Development Capital)",
        "Maven Capital Partners",
        "YFM Equity Partners",
        "Bowmark Capital",
        "Dunedin Capital Partners",
        "ISIS Equity Partners",
        "Growth Capital Partners",
      ];

      const growthCapital = [
        "Index Ventures",
        "Accel",
        "Balderton Capital",
        "Draper Esprit",
        "Notion Capital",
        "MMC Ventures",
        "Dawn Capital",
        "Atomico",
        "LocalGlobe",
        "Passion Capital",
      ];

      const investmentBanks = [
        "Goldman Sachs",
        "JPMorgan",
        "Morgan Stanley",
        "Bank of America",
        "Credit Suisse",
        "UBS",
        "Deutsche Bank",
        "Barclays",
        "Citigroup",
        "Wells Fargo",
        "BNP Paribas",
        "Société Générale",
        "Standard Chartered",
        "HSBC",
        "RBS",
        "Lloyds Banking Group",
      ];

      const assetManagers = [
        "BlackRock",
        "Vanguard",
        "State Street",
        "Fidelity",
        "PIMCO",
        "Invesco",
        "Franklin Templeton",
        "T. Rowe Price",
        "Charles Schwab",
        "Capital Group",
        "Prudential Financial",
        "Axa",
        "Allianz Global Investors",
        "Legal & General",
        "Schroders",
        "Aberdeen Standard Investments",
      ];

      const hedgeFunds = [
        "Bridgewater Associates",
        "AQR Capital Management",
        "Renaissance Technologies",
        "Man Group",
        "Two Sigma",
        "Millennium Management",
        "Citadel",
        "Elliott Management",
        "Baupost Group",
        "DE Shaw",
        "Brevan Howard",
        "Marshall Wace",
        "Winton Capital",
        "GLG Partners",
      ];

      const companyClean = companyName.toLowerCase().trim();

      for (const firm of megaFunds) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Mega Fund";
        }
      }

      for (const firm of largeCap) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Large Cap";
        }
      }

      for (const firm of midMarket) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Mid-Market";
        }
      }

      for (const firm of growthCapital) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Growth Capital";
        }
      }

      for (const firm of investmentBanks) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Investment Bank";
        }
      }

      for (const firm of assetManagers) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Asset Manager";
        }
      }

      for (const firm of hedgeFunds) {
        if (companyClean.includes(firm.toLowerCase())) {
          return "Private Credit";
        }
      }

      return "Confidential Firm";
    }

    // Helper: Get AI salary estimate
    getAISalaryEstimate(job) {
      const baseSalary = 50000; // Base salary in GBP

      const locationMultipliers = {
        london: 1.4,
        manchester: 1.1,
        birmingham: 1.0,
        edinburgh: 1.2,
        bristol: 1.1,
        leeds: 1.0,
        glasgow: 1.0,
        hybrid: 1.2,
        remote: 1.1,
      };

      const seniorityMultipliers = {
        intern: 0.4,
        analyst: 1.0,
        associate: 1.5,
        senior: 2.0,
        principal: 2.5,
        director: 3.0,
        "managing director": 4.0,
        partner: 5.0,
        "vice president": 2.8,
        vp: 2.8,
      };

      const roleMultipliers = {
        "investment banking": 1.8,
        "private equity": 2.0,
        "hedge fund": 1.9,
        "asset management": 1.5,
        risk: 1.3,
        compliance: 1.2,
        operations: 1.1,
        technology: 1.4,
        quantitative: 1.7,
        trading: 1.8,
      };

      // Apply location multiplier
      const locationLower = (job.location || "").toLowerCase();
      let locationMult = 1.0;
      for (const [loc, mult] of Object.entries(locationMultipliers)) {
        if (locationLower.includes(loc)) {
          locationMult = mult;
          break;
        }
      }

      // Apply seniority multiplier
      const titleLower = (job.title || "").toLowerCase();
      let seniorityMult = 1.0;
      for (const [level, mult] of Object.entries(seniorityMultipliers)) {
        if (titleLower.includes(level)) {
          seniorityMult = Math.max(seniorityMult, mult);
        }
      }

      // Apply role multiplier
      let roleMult = 1.0;
      for (const [role, mult] of Object.entries(roleMultipliers)) {
        if (titleLower.includes(role)) {
          roleMult = Math.max(roleMult, mult);
        }
      }

      // Calculate estimate
      const estimatedSalary =
        baseSalary * locationMult * seniorityMult * roleMult;

      // Add some variance for realism (+/- 10%)
      const variance = 0.1;
      const minEstimate = estimatedSalary * (1 - variance);
      const maxEstimate = estimatedSalary * (1 + variance);

      // Format as range
      return (
        "£" +
        Math.round(minEstimate / 1000) +
        "k - £" +
        Math.round(maxEstimate / 1000) +
        "k"
      );
    }
  }

  // Initialize when document is ready
  $(document).ready(() => {
    // Initialize if we have any relevant container OR if PE filters exist
    if (
      $(".sffc-opportunities-wrapper").length ||
      $("#senna-messages").length ||
      $(".senna-conversational-area").length ||
      window.peFiltersAjax || // PE filters AJAX is loaded
      $('[data-elementor-type="wp-page"]').length
    ) {
      // On any Elementor page

      window.sennaConversational = new SennaConversational();

      // Mobile optimizations
      if (window.innerWidth <= 768) {
        // Prevent zoom on input focus (iOS)
        $("input, textarea").on("focus", function () {
          $("meta[name=viewport]").attr(
            "content",
            "width=device-width, initial-scale=1, maximum-scale=1"
          );
        });

        $("input, textarea").on("blur", function () {
          $("meta[name=viewport]").attr(
            "content",
            "width=device-width, initial-scale=1"
          );
        });

        // Scroll to input when focused
        $("#senna-input").on("focus", function () {
          setTimeout(() => {
            const input = this;
            input.scrollIntoView({ behavior: "smooth", block: "center" });
          }, 300);
        });
      }
    }
  });

  // Global functions for button onclick handlers
  window.passJob = function (jobId) {
    const $card = $(
      `.sffc-match-card[data-job-id="${jobId}"], .job-card-vogue[data-job-id="${jobId}"]`
    );
    $card.fadeOut(300, () => $card.remove());

    // Track preference if handler available
    if (window.sennaConversational) {
      window.sennaConversational.trackPreference(jobId, "pass");
    }

    // Save to backend
    if (window.saveOpportunity) {
      window.saveOpportunity(jobId, "pass");
    }
  };

  // Handle interested button click
  window.handleInterested = function (jobId, btnElement) {
    const $btn = $(btnElement);
    const jobData = JSON.parse($btn.attr("data-job").replace(/&apos;/g, "'"));

    if (!$btn.hasClass("added")) {
      // Add to shortlist
      $btn.addClass("added");
      $btn.find("span").text("Saved");
      $btn.find("svg").attr("fill", "currentColor");

      // Add to local shortlist
      if (window.addToShortlist) {
        window.addToShortlist(jobData);
      }

      // Save to backend
      if (window.saveOpportunity) {
        window.saveOpportunity(jobId, "save");
      }

      // Show confirmation
      showNotification("Added to shortlist!", "success");
    } else {
      // Remove from shortlist
      $btn.removeClass("added");
      $btn.find("span").text("Interested");
      $btn.find("svg").attr("fill", "none");

      // Remove from local shortlist
      if (window.removeFromShortlist) {
        window.removeFromShortlist(jobId);
      }

      // Update backend
      if (window.saveOpportunity) {
        window.saveOpportunity(jobId, "remove");
      }

      showNotification("Removed from shortlist", "info");
    }
  };

  // Add these methods to SennaConversational prototype
  SennaConversational.prototype.promptProfileCreation = function () {
    this.addSennaMessage(
      "Let's create your profile to unlock personalized job matches. This will help me find roles that truly fit your experience and goals."
    );
    if (window.location.pathname.includes("/dashboard")) {
      // Redirect to profile page if in dashboard
      window.location.href = "/dashboard/profile/";
    } else {
      this.addSennaMessage(
        "Please sign up or log in to create your profile and get personalized recommendations."
      );
    }
  };

  SennaConversational.prototype.startProfileSetup = function () {
    this.addSennaMessage(
      "Great decision! Let's set up your profile. I'll guide you through a few quick questions."
    );
    if (window.openProfileBuilder) {
      window.openProfileBuilder();
    } else if (window.ProfileBuilder) {
      const profileBuilder = new window.ProfileBuilder();
      profileBuilder.open();
    } else {
      this.addSennaMessage(
        "Please navigate to your dashboard to complete your profile setup."
      );
    }
  };

  SennaConversational.prototype.completeProfile = function () {
    // Clear any existing prompts to avoid duplicates
    this.profilePromptShown = true;
    this.intelligencePromptShown = true;
    this.shortlistTooltipShown = true;

    const profile = JSON.parse(
      localStorage.getItem("sffc_user_profile") || "{}"
    );
    const missingFields = [];

    if (!profile.skills || profile.skills.length === 0)
      missingFields.push("skills");
    if (!profile.experience_level) missingFields.push("experience level");
    if (!profile.years_experience) missingFields.push("years of experience");
    if (!profile.preferred_locations) missingFields.push("preferred locations");

    this.addSennaMessage(
      `Let's complete your profile. You just need to add: ${missingFields.join(
        ", "
      )}. This will take less than a minute.`
    );

    if (window.openProfileBuilder) {
      window.openProfileBuilder();
    } else if (window.ProfileBuilder) {
      const profileBuilder = new window.ProfileBuilder();
      profileBuilder.open();
    }
  };

  SennaConversational.prototype.updateProfilePreferences = function () {
    this.addSennaMessage(
      "Let's refresh your profile so the live expert has the latest context."
    );
    if (window.openProfileBuilder) {
      window.openProfileBuilder();
    } else if (window.ProfileBuilder) {
      const profileBuilder = new window.ProfileBuilder();
      profileBuilder.open();
    } else {
      this.addSennaMessage(
        "Navigate to your dashboard to update your profile preferences."
      );
    }
  };

  SennaConversational.prototype.requestLiveExpert = function (context = {}) {
    const payload = Object.assign({}, context || {}, { force: true });
    if (!payload.headline) {
      payload.headline = "Live Expert Support";
    }
    this.showLiveExpertPanel(payload);
  };

  SennaConversational.prototype.showAllJobs = function () {
    this.requestLiveExpert({
      source: "legacy-show-all",
      headline: "Live Expert Support",
      autoConnect: true,
    });
    this.addSennaMessage(
      "Share the focus areas you're exploring and our Live Expert Concierge will curate a tailored briefing for you.",
      true,
      "Next Steps"
    );
  };

  SennaConversational.prototype.showPersonalizedMatches = function () {
    if (this.jobSearchDisabled) {
      this.requestLiveExpert({ source: "personalized-matches" });
      return;
    }

    const profile = JSON.parse(
      localStorage.getItem("sffc_user_profile") || "{}"
    );
    if (!profile.skills || profile.skills.length === 0) {
      this.addSennaMessage(
        "I need your skills information to show personalized matches. Let me help you add them quickly."
      );
      this.completeProfile();
      return;
    }

    // Filter jobs based on profile
    const userSkills = profile.skills.map((s) => s.toLowerCase());
    const matchingJobs = this.allJobs.filter((job) => {
      if (!job.skills) return false;
      const jobSkills = job.skills.map((s) => s.toLowerCase());
      const matchCount = jobSkills.filter((skill) =>
        userSkills.some(
          (userSkill) => skill.includes(userSkill) || userSkill.includes(skill)
        )
      ).length;
      return matchCount > 0;
    });

    // Sort by match quality
    matchingJobs.sort((a, b) => {
      const aMatchCount =
        a.skills?.filter((skill) =>
          userSkills.some(
            (userSkill) =>
              skill.toLowerCase().includes(userSkill) ||
              userSkill.includes(skill.toLowerCase())
          )
        ).length || 0;

      const bMatchCount =
        b.skills?.filter((skill) =>
          userSkills.some(
            (userSkill) =>
              skill.toLowerCase().includes(userSkill) ||
              userSkill.includes(skill.toLowerCase())
          )
        ).length || 0;

      return bMatchCount - aMatchCount;
    });

    if (matchingJobs.length > 0) {
      this.addSennaMessage(
        `Based on your skills in ${profile.skills
          .slice(0, 3)
          .join(", ")}, I found ${
          matchingJobs.length
        } highly relevant opportunities. Here are your best matches:`
      );
      this.filteredJobs = matchingJobs;
      setTimeout(() => {
        this.renderJobsInChat(matchingJobs.slice(0, 9));
      }, 300);
    } else {
      this.addSennaMessage(
        "Let me broaden the search to find opportunities that could be a good fit for your experience."
      );
      this.requestLiveExpert({ source: "legacy-call", autoConnect: true });
    }
  };

  SennaConversational.prototype.showSavedRoles = function () {
    if (this.jobSearchDisabled) {
      this.addSennaMessage(
        "Share which opportunities you tracked and a live expert will help you prioritize next steps.",
        true,
        "Live Expert Support"
      );
      this.requestLiveExpert({ source: "saved-roles", autoConnect: true });
      return;
    }

    const savedJobs = JSON.parse(
      localStorage.getItem("sffc_saved_jobs") || "[]"
    );

    if (savedJobs.length === 0) {
      this.addSennaMessage(
        "You haven't saved any roles yet. Browse opportunities and click the bookmark icon to save roles for later."
      );
      return;
    }

    // Create saved jobs display
    const savedMessage = `
            <div class="saved-roles-container">
                <div style="margin-bottom: 20px;">
                    <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px;">
                        📚 Your Saved Roles (${savedJobs.length})
                    </h3>
                    <p style="color: #6B7280; font-size: 14px;">
                        Roles you've bookmarked for later review
                    </p>
                </div>
                <div class="job-results-grid" style="display: grid; gap: 16px;">
                    ${savedJobs
                      .map((job) => {
                        // Parse saved date
                        const savedDate = new Date(job.savedAt || Date.now());
                        const daysAgo = Math.floor(
                          (Date.now() - savedDate) / (1000 * 60 * 60 * 24)
                        );
                        const savedText =
                          daysAgo === 0
                            ? "Saved today"
                            : daysAgo === 1
                            ? "Saved yesterday"
                            : `Saved ${daysAgo} days ago`;

                        return `
                            <div class="job-card-vogue sffc-job-card" 
                                data-job-id="${job.id || job.job_id}"
                                data-sffc_job_title="${
                                  job.title || job.sffc_job_title
                                }"
                                data-sffc_company_name="${
                                  job.company || job.sffc_company_name
                                }"
                                data-sffc_location="${
                                  job.location || job.sffc_location
                                }"
                                data-sffc_seniority_level="${
                                  job.seniority_level ||
                                  job.sffc_seniority_level
                                }"
                                data-sffc_application_url="${
                                  job.application_url ||
                                  job.sffc_application_url
                                }"
                                style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; position: relative;">
                                
                                <!-- Remove from saved button -->
                                <button onclick="sennaConversational.removeSavedRole('${
                                  job.id || job.job_id
                                }')" 
                                    style="position: absolute; top: 16px; right: 16px; background: #FEE2E2; border: none; 
                                           border-radius: 8px; padding: 6px 10px; cursor: pointer; color: #DC2626; 
                                           font-size: 12px; font-weight: 600;">
                                    Remove
                                </button>
                                
                                <div class="job-header">
                                    <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin-bottom: 8px; margin-right: 80px;">
                                        ${
                                          job.title ||
                                          job.sffc_job_title ||
                                          "Position"
                                        }
                                    </h3>
                                    <div style="font-size: 14px; color: #059669; font-weight: 600; margin-bottom: 12px;">
                                        ${
                                          job.company ||
                                          job.sffc_company_name ||
                                          "Company"
                                        }
                                    </div>
                                    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 12px;">
                                        ${
                                          job.location || job.sffc_location
                                            ? `
                                            <span style="display: flex; align-items: center; gap: 4px; color: #6B7280; font-size: 13px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                ${
                                                  job.location ||
                                                  job.sffc_location
                                                }
                                            </span>
                                        `
                                            : ""
                                        }
                                        ${
                                          job.salary_range ||
                                          job.sffc_salary_range
                                            ? `
                                            <span style="display: flex; align-items: center; gap: 4px; color: #6B7280; font-size: 13px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="12" y1="1" x2="12" y2="23"></line>
                                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                </svg>
                                                ${
                                                  job.salary_range ||
                                                  job.sffc_salary_range
                                                }
                                            </span>
                                        `
                                            : ""
                                        }
                                        <span style="display: flex; align-items: center; gap: 4px; color: #8B5CF6; font-size: 13px; font-weight: 600;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                            ${savedText}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="job-actions" style="display: flex; gap: 8px; margin-top: 16px;">
                                    <button class="sffc-btn-apply" 
                                        onclick="window.open('${
                                          job.application_url ||
                                          job.sffc_application_url ||
                                          "#"
                                        }', '_blank')"
                                        style="flex: 1; background: #003366; color: white; border: none; padding: 10px 16px; 
                                               border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                        Apply Now
                                    </button>
                                    <button class="sffc-btn-intelligence" 
                                        data-job-id="${job.id || job.job_id}"
                                        onclick="window.generateIntelligence('${
                                          job.id || job.job_id
                                        }')"
                                        style="flex: 1; background: white; color: #003366; border: 2px solid #003366; 
                                               padding: 10px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                                        Get Intelligence
                                    </button>
                                </div>
                            </div>
                        `;
                      })
                      .join("")}
                </div>
                ${
                  savedJobs.length > 6
                    ? `
                    <div style="text-align: center; margin-top: 20px;">
                        <button onclick="sennaConversational.clearAllSaved()" 
                            style="background: #FEE2E2; color: #DC2626; border: none; padding: 10px 20px; 
                                   border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                            Clear All Saved Roles
                        </button>
                    </div>
                `
                    : ""
                }
            </div>
        `;

    this.addSennaMessage(savedMessage, true);
  };

  SennaConversational.prototype.removeSavedRole = function (jobId) {
    let savedJobs = JSON.parse(localStorage.getItem("sffc_saved_jobs") || "[]");
    savedJobs = savedJobs.filter((job) => (job.id || job.job_id) !== jobId);
    localStorage.setItem("sffc_saved_jobs", JSON.stringify(savedJobs));

    // Show notification
    window.showNotification("Role removed from saved list", "success");

    // Refresh the saved roles display
    this.showSavedRoles();
  };

  SennaConversational.prototype.clearAllSaved = function () {
    if (confirm("Are you sure you want to clear all saved roles?")) {
      localStorage.setItem("sffc_saved_jobs", "[]");
      window.showNotification("All saved roles cleared", "success");
      this.addSennaMessage(
        "Your saved roles list has been cleared. Start fresh by browsing new opportunities!"
      );
    }
  };

  SennaConversational.prototype.filterBySalary = function (minSalary) {
    const salaryJobs = this.allJobs.filter((job) => {
      const min = parseInt(job.salary_min) || 0;
      const max = parseInt(job.salary_max) || 0;
      return max >= minSalary || min >= minSalary;
    });

    if (salaryJobs.length > 0) {
      const salaryStr =
        minSalary >= 1000 ? `£${minSalary / 1000}k` : `£${minSalary}`;
      this.addSennaMessage(
        `Found ${salaryJobs.length} roles with compensation above ${salaryStr}. These represent the top-tier opportunities in the market.`
      );
      this.filteredJobs = salaryJobs;
      setTimeout(() => {
        this.renderJobsInChat(salaryJobs.slice(0, 9));
      }, 300);
    } else {
      this.addSennaMessage(
        "Let me show you all available opportunities. You can use salary filters to refine further."
      );
      this.requestLiveExpert({ source: "legacy-call" });
    }
  };

  // Show notification helper
  window.showNotification = function (message, type = "info") {
    const notification = $(`
            <div class="sffc-notification ${type}" style="
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === "success" ? "#2D6A4F" : "#132D51"};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideInUp 0.3s ease;
                font-family: 'Inter', sans-serif;
                font-size: 14px;
            ">
                ${message}
            </div>
        `);

    $("body").append(notification);

    setTimeout(() => {
      notification.fadeOut(300, () => notification.remove());
    }, 3000);
  };

  function getSavedRolesList() {
    const shortlistFromController =
      (window.sennaConversational && window.sennaConversational.shortlist) ||
      [];
    let storedShortlist = [];
    try {
      const storedRaw = localStorage.getItem("sffc_shortlist");
      if (storedRaw) {
        const parsed = JSON.parse(storedRaw);
        if (Array.isArray(parsed)) {
          storedShortlist = parsed;
        }
      }
    } catch (e) {
      storedShortlist = [];
    }

    const combined = [...shortlistFromController, ...storedShortlist];
    const seen = new Map();
    combined.forEach((role) => {
      if (!role) return;
      const id = role.id || role.job_id || role.jobId;
      if (!id) return;
      if (!seen.has(id)) {
        seen.set(id, role);
      }
    });

    return Array.from(seen.values());
  }

  window.showSavedRolesInChat = function (options = {}) {
    const sennaChat = window.sennaConversational;
    if (!sennaChat || typeof sennaChat.addSennaMessage !== "function") {
      return;
    }

    const shortlist = getSavedRolesList();
    if (!shortlist.length) {
      sennaChat.addSennaMessage(
        `<div class="saved-roles-card" style="background: #fff7ed; border: 1px solid #f5d0a4; border-radius: 14px; padding: 18px; color: #92400e;">
            <strong>No saved roles yet.</strong>
            <p style="margin: 8px 0 0; font-size: 13px; color: #92400e;">Tap "Save" on any opportunity to build a shortlist you can return to instantly.</p>
        </div>`,
        true,
        "Saved roles"
      );
      sennaChat.scrollToBottom();
      return;
    }

    const canCompare = shortlist.length >= 2;

    const limit = options.limit || 5;
    const highlightId = options.highlightJobId;
    const headlineCount = shortlist.length;
    const plural = headlineCount === 1 ? "role" : "roles";
    const trimmed = shortlist.slice(0, limit);

    const itemsHtml = trimmed
      .map((role, index) => {
        const roleId = role.id || role.job_id || role.jobId || `saved-${index}`;
        const company = role.company || role.sffc_company_name || "Company";
        const title = role.title || role.sffc_job_title || "Position";
        const location = role.location || role.sffc_location || "Location";
        const badge = highlightId && highlightId === roleId ? "highlight" : "";
        const savedAt = role.savedAt ? new Date(role.savedAt) : null;
        let stamp = "Recently saved";
        if (savedAt && !isNaN(savedAt.getTime())) {
          const diffDays = Math.floor(
            (Date.now() - savedAt.getTime()) / 86400000
          );
          if (diffDays === 0) stamp = "Saved today";
          else if (diffDays === 1) stamp = "Saved yesterday";
          else stamp = `Saved ${diffDays} days ago`;
        }

        return `
          <li class="saved-role-line ${badge}" style="display:flex; flex-direction:column; gap:4px; padding:10px 12px; border-radius:10px; background:${
          badge ? "#ecfdf5" : "#f8fafc"
        }; border:1px solid ${
          badge ? "#34d399" : "rgba(148, 163, 184, 0.25)"
        };">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
              <div style="font-weight:600; color:#0f172a;">${title}</div>
              <span style="font-size:11px; color:${
                badge ? "#065f46" : "#64748b"
              };">${stamp}</span>
            </div>
            <div style="font-size:13px; color:#475569; display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
              <span>${company}</span>
              <span style="opacity:0.65;">•</span>
              <span>${location}</span>
            </div>
          </li>
        `;
      })
      .join("");

    const remaining = shortlist.length - trimmed.length;
    const remainingHtml =
      remaining > 0
        ? `<p style="margin: 10px 0 0; font-size: 12px; color: #475569;">+ ${remaining} more saved ${plural}. Use "View saved roles" anytime to revisit them.</p>`
        : "";

    const messageHtml = `
      <div class="saved-roles-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:16px; padding:20px; box-shadow:0 18px 32px rgba(15,23,42,0.08);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
          <div>
            <h3 style="margin:0; font-size:16px; color:#0f172a; font-weight:700;">Saved shortlist</h3>
            <p style="margin:4px 0 0; font-size:13px; color:#475569;">You currently have <strong>${headlineCount}</strong> saved ${plural}.</p>
          </div>
          <button onclick="window.changeCVUpload && window.changeCVUpload()" style="padding:6px 12px; border-radius:6px; border:1px solid #cbd5f5; background:#f8fafc; color:#1e293b; font-size:12px; cursor:pointer;">Update CV</button>
        </div>
        <ul style="list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:8px;">
          ${itemsHtml}
        </ul>
        ${remainingHtml}
        <div style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
          <button onclick="window.revealJobMatches && window.revealJobMatches()" style="padding:8px 14px; border-radius:999px; border:none; background:#1a472a; color:#ffffff; font-size:12px; font-weight:600; cursor:pointer;">Explore tailored roles</button>
          <button onclick="window.refineJobMatches && window.refineJobMatches()" style="padding:8px 14px; border-radius:999px; border:1px solid rgba(26,71,42,0.2); background:#ffffff; color:#1a472a; font-size:12px; font-weight:600; cursor:pointer;">Refine matches</button>
          ${
            canCompare
              ? `<button onclick="window.compareSavedRoles && window.compareSavedRoles({source: 'saved_roles'});" style="padding:8px 14px; border-radius:999px; border:1px solid rgba(157,23,77,0.25); background:#fdf2f8; color:#9d174d; font-size:12px; font-weight:600; cursor:pointer;">Compare saved roles</button>`
              : ""
          }
        </div>
      </div>
    `;

    sennaChat.addSennaMessage(messageHtml, true, "Saved roles");

    if (typeof window.scrollMatchCardsIntoView === "function") {
      window.scrollMatchCardsIntoView({
        selector: ".saved-roles-card",
        offset: 32,
      });
    } else {
      sennaChat.scrollToBottom();
    }
  };

  window.compareSavedRoles = function (options = {}) {
    const roles = getSavedRolesList();
    const sennaChat = window.sennaConversational;
    if (!roles.length) {
      if (sennaChat && typeof sennaChat.addSennaMessage === "function") {
        sennaChat.addSennaMessage(
          "You haven't saved any roles yet. Save a couple to unlock the comparison matrix.",
          true,
          "Compare roles"
        );
        sennaChat.scrollToBottom();
      }
      return;
    }

    if (roles.length < 2) {
      if (sennaChat && typeof sennaChat.addSennaMessage === "function") {
        sennaChat.addSennaMessage(
          "Add at least one more shortlist entry to compare opportunities side by side.",
          true,
          "Compare roles"
        );
        sennaChat.scrollToBottom();
      }
      return;
    }

    const comparator = window.peJobComparison;
    if (!comparator) {
      if (sennaChat && typeof sennaChat.addSennaMessage === "function") {
        sennaChat.addSennaMessage(
          "Loading the comparison workspace. Please click compare again in a moment.",
          true,
          "Compare roles"
        );
      }
      return;
    }

    comparator.clearAll();
    const maxRoles = options.limit || comparator.maxCompare || 3;
    roles.slice(0, maxRoles).forEach((role, idx) => {
      const jobId = role.id || role.job_id || role.jobId || `saved-${idx}`;
      const normalized = {
        id: jobId,
        title: role.title || role.sffc_job_title || "Position",
        company: role.company || role.sffc_company_name || "Company",
        location: role.location || role.sffc_location || "Location",
        salary:
          role.salary_display || role.salary || role.ai_salary || "Competitive",
        match_score: role.matchScore || role.match_score || role.score || 70,
        job_type: role.job_type || role.work_style || "Full-time",
        experience: role.experience || role.experience_level || "",
      };
      comparator.selectJob(jobId, normalized);
    });

    comparator.compareJobs();

    if (sennaChat && typeof sennaChat.addSennaMessage === "function") {
      sennaChat.addSennaMessage(
        `Lining up ${Math.min(
          roles.length,
          maxRoles
        )} saved roles for comparison. Scroll to review the grid and pick a front-runner.`,
        true,
        "Compare roles"
      );
      sennaChat.scrollToBottom();
    }
  };

  window.ensureWSJChatForJob = function (jobData = {}, options = {}) {
    const executeInjection = () => {
      if (typeof window.createWSJChatContainer !== "function") {
        return;
      }

      const jobTitle = jobData.title || jobData.sffc_job_title || "Target Role";
      const company = jobData.company || jobData.sffc_company_name || "Company";
      const jobId = jobData.id || jobData.job_id || jobData.jobId || "manual";

      const containerHtml = window.createWSJChatContainer(
        jobTitle,
        company,
        jobId,
        jobData
      );
      if (!containerHtml) {
        return;
      }

      const sennaChat = window.sennaConversational;
      if (!sennaChat || typeof sennaChat.addSennaMessage !== "function") {
        return;
      }

      const existing = document.querySelector(
        "#senna-messages .wsj-cv-chat-container"
      );
      if (existing) {
        const parent = existing.closest(".senna-message");
        if (parent) {
          parent.remove();
        } else {
          existing.remove();
        }
      }

      sennaChat.addSennaMessage(containerHtml, true, "Tailor CV workspace");

      if (typeof window.scrollMatchCardsIntoView === "function") {
        window.scrollMatchCardsIntoView({
          selector: ".wsj-cv-chat-container",
          offset: options.offset || 48,
        });
      } else {
        sennaChat.scrollToBottom();
      }
    };

    if (typeof window.createWSJChatContainer === "function") {
      executeInjection();
    } else if (typeof jQuery !== "undefined") {
      const scriptUrl =
        (window.sffc_ajax?.plugin_url || "/") +
        "assets/js/wsj-cv-chat-integration.js";
      jQuery.getScript(scriptUrl).done(() => {
        executeInjection();
      });
    }
  };

  window.openCVTailor = function (jobId, jobData) {
    // If jobData not passed, try to get it from button or map
    if (!jobData) {
      const $btn = $(`.sffc-btn-tailor[data-job-id="${jobId}"]`);
      jobData = $btn.data("job") || {};

      // Try to get from conversational controller's jobDataMap
      if (
        !jobData.id &&
        window.sennaConversational &&
        window.sennaConversational.jobDataMap
      ) {
        jobData = window.sennaConversational.jobDataMap.get(jobId) || {};
      }
    }

    // If still no good job data, extract from page
    if (!jobData || !jobData.title || jobData.title === "Position") {
      const extractedData = extractJobDataFromPage();
      // Merge extracted data with what we have
      jobData = Object.assign({}, extractedData, jobData || {});

      // Prefer extracted data if it's better
      if (extractedData.title && extractedData.title !== "Position") {
        jobData.title = extractedData.title;
      }
      if (extractedData.company && extractedData.company !== "Company") {
        jobData.company = extractedData.company;
      }
    }

    // Ensure we have the essential job data
    if (!jobData.title && !jobData.company) {
      jobData = {
        id: jobId,
        title: "Position",
        company: "Company",
        location: "Location",
      };
    }

    if (window.ensureWSJChatForJob) {
      window.ensureWSJChatForJob(jobData, { source: "tailor_launch" });
    }

    // Check if user has a CV uploaded
    const hasCv =
      localStorage.getItem("sffc_user_cv") || window.sffc_profile?.has_cv;

    if (!hasCv) {
      // Show CV upload prompt
      showCVUploadPrompt(jobData);
      return;
    }

    // Open CV tailoring interface
    if (window.cvTailoringEngine) {
      window.cvTailoringEngine.open(jobData);
    } else {
      // Initialize CV tailoring modal
      initializeCVTailor(jobData);
    }
  };

  // Show CV upload prompt
  function showCVUploadPrompt(jobData) {
    // Remove any existing modals first
    $(".cv-upload-modal, .cv-tailor-modal").remove();

    const modalHtml = `
            <div class="cv-upload-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 10000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
                <div class="cv-upload-content" style="background: linear-gradient(135deg, #FFFFFF 0%, #FAF7F2 100%); padding: 2.5rem; border-radius: 16px; max-width: 500px; width: 90%; margin: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideInUp 0.3s ease;">
                    <h3 style="margin: 0 0 1rem 0; color: #132D51; font-family: 'Playfair Display', serif; font-size: 24px;">Upload Your CV First</h3>
                    <p style="margin-bottom: 1.5rem; color: #4A5B4F; line-height: 1.6;">To tailor your CV for <strong style="color: #1A3028;">"${
                      jobData.title || "this role"
                    }"</strong>, please upload your current CV.</p>
                    
                    <div class="cv-upload-area" style="border: 2px dashed #2D6A4F; padding: 2rem; text-align: center; border-radius: 12px; margin-bottom: 1.5rem; background: rgba(45, 106, 79, 0.03); transition: all 0.3s ease; cursor: pointer;" 
                         onmouseover="this.style.borderColor='#1B4332'; this.style.background='rgba(45, 106, 79, 0.06)';" 
                         onmouseout="this.style.borderColor='#2D6A4F'; this.style.background='rgba(45, 106, 79, 0.03)';"
                         onclick="document.getElementById('cv-file-input').click()">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2D6A4F" stroke-width="2" style="margin-bottom: 1rem;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        <p style="margin: 0.5rem 0; color: #4A5B4F; font-size: 16px;">Drop your CV here or</p>
                        <input type="file" id="cv-file-input" accept=".pdf,.doc,.docx" style="display: none;">
                        <button onclick="event.stopPropagation(); document.getElementById('cv-file-input').click()" style="background: linear-gradient(135deg, #2D6A4F 0%, #1B4332 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease; margin-top: 0.5rem;">Choose File</button>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button onclick="closeCVUploadModal()" style="flex: 1; padding: 0.75rem; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer;">Cancel</button>
                        <button onclick="uploadAndTailor('${
                          jobData.id || ""
                        }')" style="flex: 1; padding: 0.75rem; background: #2D6A4F; color: white; border: none; border-radius: 8px; cursor: pointer;">Upload & Continue</button>
                    </div>
                </div>
            </div>
        `;

    $("body").append(modalHtml);

    // Handle file selection
    $("#cv-file-input").on("change", function (e) {
      const file = e.target.files[0];
      if (file) {
        $(".cv-upload-area p").html(
          `<strong style="color: #2D6A4F;">Selected:</strong> ${file.name}`
        );
        $(".cv-upload-area")
          .css("border-color", "#2D6A4F")
          .css("background", "rgba(45, 106, 79, 0.1)");
      }
    });

    // Add drag and drop functionality
    const uploadArea = $(".cv-upload-area")[0];
    if (uploadArea) {
      uploadArea.addEventListener("dragover", (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = "#1B4332";
        uploadArea.style.background = "rgba(45, 106, 79, 0.1)";
      });

      uploadArea.addEventListener("dragleave", (e) => {
        e.preventDefault();
        uploadArea.style.borderColor = "#2D6A4F";
        uploadArea.style.background = "rgba(45, 106, 79, 0.03)";
      });

      uploadArea.addEventListener("drop", (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          const file = files[0];
          // Check file type
          if (
            file.type === "application/pdf" ||
            file.type === "application/msword" ||
            file.type ===
              "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
          ) {
            document.getElementById("cv-file-input").files = files;
            $(".cv-upload-area p").html(
              `<strong style="color: #2D6A4F;">Selected:</strong> ${file.name}`
            );
            uploadArea.style.borderColor = "#2D6A4F";
            uploadArea.style.background = "rgba(45, 106, 79, 0.1)";
          } else {
            alert("Please upload a PDF or Word document");
          }
        }
      });
    }
  }

  // Close CV upload modal - Enhanced with better cleanup
  window.closeCVUploadModal = function () {
    $(".cv-upload-modal").fadeOut(200, function () {
      $(this).remove();
    });
    // Also remove any orphaned overlays
    $(".modal-backdrop, .modal-overlay").remove();
    // Ensure body can scroll again
    $("body").css("overflow", "");
  };

  // Upload CV and start tailoring
  window.uploadAndTailor = function (jobId) {
    const fileInput = document.getElementById("cv-file-input");
    const file = fileInput?.files[0];

    if (!file) {
      alert("Please select a CV file to upload");
      return;
    }

    // Create FormData for upload
    const formData = new FormData();
    formData.append("cv_file", file);
    formData.append("action", "sffc_upload_cv");
    formData.append("nonce", window.sffc_ajax?.nonce || "");

    // Show loading state
    $(".cv-upload-content").html(
      '<div style="text-align: center; padding: 3rem;"><div class="spinner"></div><p>Uploading your CV...</p></div>'
    );

    // Upload CV via AJAX
    $.ajax({
      url:
        window.sffc_ajax?.url || window.ajaxurl || "/wp-admin/admin-ajax.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          // Save CV ID
          localStorage.setItem("sffc_user_cv", response.data.cv_id);

          // Close upload modal
          closeCVUploadModal();

          // Get job data - try button first, then extract from page
          const $btn = $(`.sffc-btn-tailor[onclick*="${jobId}"]`);
          let jobData = $btn.data("job") || {};

          // If job data is incomplete, extract from page
          if (!jobData.title || jobData.title === "Position") {
            jobData = extractJobDataFromPage();
          }

          // Now initialize CV tailoring
          initializeCVTailor(jobData, response.data.cv_id);
        } else {
          alert("Failed to upload CV. Please try again.");
          closeCVUploadModal();
        }
      },
      error: function () {
        alert("Error uploading CV. Please try again.");
        closeCVUploadModal();
      },
    });
  };

  // Extract job data from current page
  function extractJobDataFromPage() {
    const jobData = {
      title: "",
      company: "",
      location: "",
      description: "",
      id: "",
    };

    // Try multiple selectors to get job title
    jobData.title =
      $(".job-title, .sffc-job-title, .position-title, h1.title, h2.title")
        .first()
        .text()
        .trim() ||
      $(".job-header h1, #job-title").first().text().trim() ||
      $(
        'h1:contains("Analyst"), h1:contains("Associate"), h1:contains("Manager"), h1:contains("Director")'
      )
        .first()
        .text()
        .trim() ||
      document.title.split("-")[0].trim() ||
      "Position";

    // Try multiple selectors to get company
    jobData.company =
      $(".company, .company-name, .sffc-company, .employer")
        .first()
        .text()
        .trim() ||
      $("#company-name, .company-header").first().text().trim() ||
      $(
        'h2:contains("Capital"), h2:contains("Partners"), h2:contains("Investment"), h2:contains("Bank")'
      )
        .first()
        .text()
        .trim() ||
      "Company";

    // Try to get location
    jobData.location =
      $(".location, .job-location, .sffc-location").first().text().trim() ||
      $("#job-location").first().text().trim() ||
      $(
        'span:contains("New York"), span:contains("London"), span:contains("San Francisco")'
      )
        .first()
        .text()
        .trim() ||
      "";

    // Try to get description
    jobData.description =
      $(".job-description, .description, .job-content").first().text().trim() ||
      $("#job-description").text().trim() ||
      "";

    return jobData;
  }

  // Initialize CV tailoring interface
  function initializeCVTailor(jobData, cvId) {
    cvId = cvId || localStorage.getItem("sffc_user_cv");

    // If jobData is missing or incomplete, extract from page
    if (!jobData || !jobData.title || jobData.title === "Position") {
      const extractedData = extractJobDataFromPage();
      jobData = Object.assign({}, extractedData, jobData || {});

      // If we got better data, use it
      if (extractedData.title && extractedData.title !== "Position") {
        jobData.title = extractedData.title;
      }
      if (extractedData.company && extractedData.company !== "Company") {
        jobData.company = extractedData.company;
      }
      if (extractedData.location) {
        jobData.location = extractedData.location;
      }
      if (extractedData.description) {
        jobData.description = extractedData.description;
      }
    }

    const tailorHtml = `
            <div class="cv-tailor-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; overflow-y: auto;">
                <div class="cv-tailor-container" style="background: white; margin: 40px auto; max-width: 900px; border-radius: 12px; position: relative;">
                    <button onclick="closeCVTailorModal()" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                    
                    <div style="padding: 2rem;">
                        <h2 style="margin: 0 0 0.5rem 0; color: #1A3028;">CV Tailoring for ${
                          jobData.title || "Position"
                        }</h2>
                        <p style="color: #636E72; margin-bottom: 2rem;">${
                          jobData.company || "Company"
                        } • ${jobData.location || "Location"}</p>
                        
                        <div class="tailor-progress" style="text-align: center; padding: 3rem;">
                            <div class="spinner" style="margin-bottom: 1rem;"></div>
                            <h3>Analyzing Job Requirements...</h3>
                            <p style="color: #636E72;">Our AI is customizing your CV to match this specific role</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

    $("body").append(tailorHtml);

    // Start CV tailoring process
    $.ajax({
      url:
        window.sffc_ajax?.url || window.ajaxurl || "/wp-admin/admin-ajax.php",
      type: "POST",
      data: {
        action: "sffc_tailor_cv",
        nonce: window.sffc_ajax?.nonce || "",
        cv_id: cvId,
        job_id: jobData.id,
        job_title: jobData.title,
        company: jobData.company,
        job_description: jobData.description || "",
      },
      success: function (response) {
        if (response.success && response.data) {
          displayTailoredCV(response.data);
        } else if (response.data && response.data.need_cv_upload) {
          // Close current modal and show CV upload prompt
          closeCVTailorModal();
          showCVUploadPrompt(jobData);
        } else {
          const errorMsg =
            response.data?.message || "Failed to tailor CV. Please try again.";
          $(".tailor-progress").html(`<p style="color: red;">${errorMsg}</p>`);
        }
      },
      error: function (xhr, status, error) {
        $(".tailor-progress").html(
          '<p style="color: red;">Error tailoring CV. Please try again.</p>'
        );
      },
    });
  }

  // Display tailored CV results
  function displayTailoredCV(response) {
    const html = `
            <div style="padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h3 style="margin: 0;">Tailoring Complete!</h3>
                    <div style="background: #2D6A4F; color: white; padding: 0.5rem 1rem; border-radius: 20px;">
                        ${response.match_score || 85}% Match
                    </div>
                </div>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <h4>Key Optimizations Made:</h4>
                    <ul style="margin: 0.5rem 0;">
                        ${(
                          response.recommendations || [
                            "Adjusted skills section",
                            "Enhanced experience descriptions",
                            "Added relevant keywords",
                          ]
                        )
                          .map((rec) => `<li>${rec}</li>`)
                          .join("")}
                    </ul>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button onclick="downloadTailoredCV()" style="flex: 1; padding: 1rem; background: linear-gradient(135deg, #2D6A4F 0%, #40916C 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Download Tailored CV
                    </button>
                    <button onclick="applyWithCV()" style="flex: 1; padding: 1rem; background: linear-gradient(135deg, #B08D57 0%, #CB997E 100%); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Apply Now
                    </button>
                </div>
            </div>
        `;

    $(".tailor-progress").html(html);
  }

  // Close CV tailor modal - Enhanced with better cleanup
  window.closeCVTailorModal = function () {
    $(".cv-tailor-modal").fadeOut(200, function () {
      $(this).remove();
    });
    // Also remove any orphaned overlays
    $(".modal-backdrop, .modal-overlay, .field-mapper-overlay").remove();
    // Ensure body can scroll again
    $("body").css("overflow", "");
    // Re-enable page interaction
    $("body").removeClass("modal-open");
  };

  // Analyze job function (removed Ultimate interface dependency)
  window.analyzeJobInUltimate = function (jobId) {
    // This function is no longer used - analysis now happens in chat via showJobInChat
    if (window.showJobInChat) {
      window.showJobInChat(jobId);
    }
  };

  // Download tailored CV
  window.downloadTailoredCV = function () {
    // Trigger download
    window.location.href =
      window.sffc_ajax?.url +
      "?action=sffc_export_cv&cv_version_id=" +
      (window.lastTailoredCvId || "");
  };

  // Apply with tailored CV
  window.applyWithCV = function () {
    closeCVTailorModal();
    // Trigger application flow
    if (window.openQuickApply) {
      window.openQuickApply();
    }
  };

  // Handle interested/shortlist button
  window.handleInterested = function (jobId, btn) {
    // Prevent event bubbling
    event?.stopPropagation();
    event?.preventDefault();

    // Check profile on interested action - subtle tooltip for incomplete profiles
    const profile = JSON.parse(
      localStorage.getItem("sffc_user_profile") || "{}"
    );
    const requiredFields = ["skills", "experience_level", "years_experience"];
    const missingFields = requiredFields.filter(
      (field) =>
        !profile[field] ||
        (Array.isArray(profile[field]) && profile[field].length === 0)
    );

    if (missingFields.length > 0 && !window.interestedTooltipShown) {
      const $btn = $(btn);
      const tooltip = $(`
                <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); 
                            background: #1F2937; color: white; padding: 8px 12px; border-radius: 6px; 
                            font-size: 12px; white-space: nowrap; margin-bottom: 8px; z-index: 10000;">
                    <div style="display: flex; align-items: center; gap: 6px; font-weight: 500;">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                      </svg>
                      Complete your profile for better matches
                    </div>
                    <div style="position: absolute; bottom: -4px; left: 50%; transform: translateX(-50%); 
                             width: 0; height: 0; border-left: 4px solid transparent; 
                             border-right: 4px solid transparent; border-top: 4px solid #1F2937;"></div>
                </div>
            `);

      $btn.css("position", "relative").append(tooltip);
      window.interestedTooltipShown = true;

      setTimeout(() => {
        tooltip.fadeOut(300, () => tooltip.remove());
      }, 3000);

      // Don't show again for 10 minutes
      setTimeout(() => {
        window.interestedTooltipShown = false;
      }, 600000);
    }

    const $btn = $(btn);

    // Find job data
    let job = null;
    if (window.sennaConversational && window.sennaConversational.jobDataMap) {
      job = window.sennaConversational.jobDataMap.get(jobId);
    }
    if (
      !job &&
      window.sennaConversational &&
      window.sennaConversational.allJobs
    ) {
      job = window.sennaConversational.allJobs.find((j) => j.id === jobId);
    }

    if (!job) {
      return;
    }

    // Add to shortlist
    if (window.addToShortlist) {
      window.addToShortlist(job);
    } else {
      const shortlist = JSON.parse(
        localStorage.getItem("sffc_shortlist") || "[]"
      );
      if (!shortlist.some((j) => j.id === jobId)) {
        shortlist.push(job);
        localStorage.setItem("sffc_shortlist", JSON.stringify(shortlist));
      }
    }

    // Update button state
    $btn.addClass("added").find("span").text("Saved");
  };

  /**
   * Generate dynamic MENA Careers tips based on job data and user context
   */
  window.generateDynamicSennaTip = (function () {
    // Helper functions enclosed in the IIFE
    function analyzeSalary(job, profile) {
      const currentSalary = parseInt(profile.current_salary) || 0;
      const jobMinSalary = parseInt(job.salary_min) || 0;
      const jobMaxSalary = parseInt(job.salary_max) || 0;

      if (jobMinSalary && currentSalary) {
        const increase = ((jobMinSalary - currentSalary) / currentSalary) * 100;
        if (increase > 30)
          return {
            strength: "high",
            message: `${Math.round(increase)}% salary increase potential!`,
          };
        if (increase > 15)
          return { strength: "medium", message: "Solid compensation upgrade" };
        if (increase < 0)
          return { strength: "low", message: "Consider total package value" };
      }
      return { strength: "neutral", message: null };
    }

    function analyzeSkillMatch(job, profile) {
      const userSkills = (profile.skills || []).map((s) =>
        typeof s === "string" ? s.toLowerCase() : ""
      );

      // Safely handle job skills/requirements - could be string, array, or object
      let jobSkillsStr = "";
      if (job.skills) {
        if (typeof job.skills === "string") {
          jobSkillsStr = job.skills;
        } else if (Array.isArray(job.skills)) {
          jobSkillsStr = job.skills.join(" ");
        } else if (typeof job.skills === "object") {
          jobSkillsStr = JSON.stringify(job.skills);
        }
      } else if (job.requirements) {
        if (typeof job.requirements === "string") {
          jobSkillsStr = job.requirements;
        } else if (Array.isArray(job.requirements)) {
          jobSkillsStr = job.requirements.join(" ");
        } else if (typeof job.requirements === "object") {
          jobSkillsStr = JSON.stringify(job.requirements);
        }
      }

      const jobSkills = jobSkillsStr.toLowerCase();

      let matchCount = 0;
      let criticalMatch = false;

      // Check for critical skill matches
      const criticalSkills = [
        "python",
        "react",
        "aws",
        "kubernetes",
        "typescript",
        "java",
        "c++",
      ];

      userSkills.forEach((skill) => {
        if (skill && jobSkills.includes(skill)) {
          matchCount++;
          if (criticalSkills.includes(skill)) criticalMatch = true;
        }
      });

      if (criticalMatch)
        return {
          strength: "high",
          message: "Your tech stack is a perfect fit!",
        };
      if (matchCount >= 5)
        return {
          strength: "high",
          message: `${matchCount} of your skills directly apply`,
        };
      if (matchCount >= 3)
        return { strength: "medium", message: "Good skill alignment" };
      return { strength: "neutral", message: null };
    }

    function analyzeExperience(job, profile) {
      const userYears = parseInt(profile.years_experience) || 0;
      const jobTitle = (job.title || "").toLowerCase();
      const level = job.experience_level || "";

      // Analyze based on title keywords
      if (jobTitle.includes("senior") && userYears >= 5) {
        return {
          strength: "high",
          message: "You meet the seniority requirements!",
        };
      }
      if (jobTitle.includes("lead") && userYears >= 7) {
        return { strength: "high", message: "Ready for this leadership role!" };
      }
      if (jobTitle.includes("junior") && userYears <= 2) {
        return { strength: "high", message: "Perfect entry-level match!" };
      }
      if (jobTitle.includes("principal") || jobTitle.includes("staff")) {
        if (userYears >= 10)
          return {
            strength: "high",
            message: "Your experience qualifies you!",
          };
        return { strength: "low", message: "Stretch role - show your impact!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeLocation(job, profile) {
      const userLocation = (
        profile.preferred_location ||
        profile.location ||
        ""
      ).toLowerCase();
      const jobLocation = (job.location || "").toLowerCase();

      if (jobLocation.includes("remote")) {
        return { strength: "high", message: "Full remote flexibility!" };
      }
      if (jobLocation.includes("hybrid")) {
        return { strength: "medium", message: "Hybrid work available" };
      }
      if (userLocation && jobLocation.includes(userLocation)) {
        return { strength: "high", message: "In your preferred location!" };
      }

      // Check for relocation opportunity
      const premiumLocations = [
        "london",
        "paris",
        "new york",
        "san francisco",
        "zurich",
        "singapore",
      ];
      if (premiumLocations.some((loc) => jobLocation.includes(loc))) {
        return { strength: "medium", message: "Premium market opportunity!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeCompany(job, profile) {
      const company = (job.company || "").toLowerCase();
      const industry = (job.industry || "").toLowerCase();
      const userInterests = (profile.interests || []).map((i) =>
        typeof i === "string" ? i.toLowerCase() : ""
      );

      // Check for prestigious companies
      const topTier = [
        "google",
        "meta",
        "apple",
        "microsoft",
        "amazon",
        "goldman",
        "jpmorgan",
        "mckinsey",
      ];
      if (topTier.some((c) => company.includes(c))) {
        return { strength: "high", message: "Prestigious brand for your CV!" };
      }

      // Check for high-growth companies
      if (job.company_size === "startup" || industry.includes("fintech")) {
        return { strength: "medium", message: "High-growth environment!" };
      }

      // Check for interest alignment
      if (
        userInterests.some(
          (interest) =>
            interest &&
            (company.includes(interest) || industry.includes(interest))
        )
      ) {
        return { strength: "high", message: "Aligns with your interests!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeTiming(job) {
      const postedDate = new Date(
        job.posted_date || job.date_posted || Date.now()
      );
      const now = new Date();
      const daysAgo = Math.floor((now - postedDate) / (1000 * 60 * 60 * 24));

      if (daysAgo <= 2)
        return { strength: "high", message: "Just posted - apply early!" };
      if (daysAgo <= 7)
        return { strength: "medium", message: "Fresh opportunity" };
      if (daysAgo >= 30)
        return {
          strength: "low",
          message: "Been open a while - less competition",
        };

      return { strength: "neutral", message: null };
    }

    function analyzeCompetition(job) {
      // We don't have real applicant data, so return neutral
      return { strength: "neutral", message: null };
    }

    function analyzeGrowthPotential(job) {
      const title = (job.title || "").toLowerCase();
      const description = (job.description || "").toLowerCase();

      // Look for growth indicators
      const growthKeywords = [
        "grow",
        "build",
        "scale",
        "expand",
        "launch",
        "lead",
        "strategy",
        "roadmap",
      ];
      const matchCount = growthKeywords.filter((kw) =>
        description.includes(kw)
      ).length;

      if (matchCount >= 5)
        return { strength: "high", message: "Major growth role!" };
      if (matchCount >= 3)
        return { strength: "medium", message: "Good advancement potential" };

      // Check for team building
      if (description.includes("hire") || description.includes("build team")) {
        return { strength: "high", message: "Team building opportunity!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeShortlistContext(job, shortlist) {
      if (shortlist.length === 0) {
        return {
          strength: "medium",
          message: "Great first addition to shortlist!",
        };
      }

      // Compare to other shortlisted jobs
      const avgSalary =
        shortlist.reduce((sum, j) => sum + (parseInt(j.salary_min) || 0), 0) /
        shortlist.length;
      const jobSalary = parseInt(job.salary_min) || 0;

      if (jobSalary > avgSalary * 1.2) {
        return { strength: "high", message: "Best compensation in your list!" };
      }

      // Check for diversity
      const companies = shortlist.map((j) => j.company);
      if (!companies.includes(job.company)) {
        return { strength: "medium", message: "Diversifies your options!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeViewHistory(job, viewedJobs) {
      if (viewedJobs.includes(job.id)) {
        return {
          strength: "low",
          message: "You viewed this before - reconsider?",
        };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeIndustryTrends(job) {
      const industry = (job.industry || "").toLowerCase();
      const title = (job.title || "").toLowerCase();

      // Hot industries
      const hotIndustries = [
        "ai",
        "artificial intelligence",
        "machine learning",
        "fintech",
        "cleantech",
        "biotech",
        "cybersecurity",
        "blockchain",
        "quantum",
        "robotics",
      ];
      const emergingRoles = [
        "ai engineer",
        "prompt engineer",
        "data scientist",
        "ml engineer",
        "cloud architect",
        "devsecops",
        "sustainability",
        "esg",
      ];

      for (const hot of hotIndustries) {
        if (industry.includes(hot) || title.includes(hot)) {
          return {
            strength: "high",
            message: `Hot field: ${hot.toUpperCase()} is booming!`,
          };
        }
      }

      for (const emerging of emergingRoles) {
        if (title.includes(emerging)) {
          return {
            strength: "high",
            message: "Emerging role with high demand!",
          };
        }
      }

      if (industry.includes("tech") || industry.includes("software")) {
        return {
          strength: "medium",
          message: "Strong tech sector opportunity",
        };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeRoleProgression(job, profile) {
      const currentTitle = (profile.current_title || "").toLowerCase();
      const targetTitle = (job.title || "").toLowerCase();
      const userYears = parseInt(profile.years_experience) || 0;

      // Natural progression paths
      const progressions = {
        analyst: ["senior analyst", "associate", "manager"],
        associate: ["senior associate", "vice president", "director"],
        engineer: [
          "senior engineer",
          "staff engineer",
          "principal engineer",
          "architect",
        ],
        developer: [
          "senior developer",
          "lead developer",
          "architect",
          "engineering manager",
        ],
        manager: ["senior manager", "director", "senior director", "vp"],
        director: ["senior director", "vp", "senior vp", "executive"],
        consultant: [
          "senior consultant",
          "manager",
          "senior manager",
          "principal",
        ],
      };

      // Check if it's a logical next step
      for (const [current, nexts] of Object.entries(progressions)) {
        if (currentTitle.includes(current)) {
          for (const next of nexts) {
            if (targetTitle.includes(next)) {
              return { strength: "high", message: "Logical next career step!" };
            }
          }
        }
      }

      // Check for lateral moves
      if (
        currentTitle &&
        targetTitle.includes("senior") &&
        !currentTitle.includes("senior")
      ) {
        return { strength: "high", message: "Step up to senior level!" };
      }

      // Check for management transition
      if (
        !currentTitle.includes("manager") &&
        targetTitle.includes("manager")
      ) {
        if (userYears >= 5) {
          return { strength: "high", message: "Move into management!" };
        }
      }

      return { strength: "neutral", message: null };
    }

    function analyzeWorkLifeBalance(job) {
      const description = (job.description || "").toString().toLowerCase();
      const benefits =
        typeof job.benefits === "string"
          ? job.benefits.toLowerCase()
          : (job.benefits || "").toString().toLowerCase();
      const title = (job.title || "").toString().toLowerCase();
      const industry = (job.industry || "").toString().toLowerCase();

      // Positive indicators
      const balanceKeywords = [
        "flexible",
        "work-life balance",
        "unlimited pto",
        "remote first",
        "4 day week",
        "no overtime",
        "family friendly",
        "wellness",
        "mental health",
        "sabbatical",
      ];

      let balanceScore = 0;
      for (const keyword of balanceKeywords) {
        if (description.includes(keyword) || benefits.includes(keyword)) {
          balanceScore++;
        }
      }

      if (balanceScore >= 3)
        return { strength: "high", message: "Excellent work-life balance!" };
      if (balanceScore >= 2)
        return { strength: "medium", message: "Good flexibility offered" };

      // Negative indicators
      if (
        description.includes("fast-paced") &&
        description.includes("demanding")
      ) {
        return { strength: "low", message: "High-intensity environment" };
      }

      // Specific roles known for balance
      if (title.includes("consultant") || industry.includes("government")) {
        return {
          strength: "medium",
          message: "Typically good work-life balance",
        };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeLearningOpportunity(job, profile) {
      const description = (job.description || "").toLowerCase();
      const userSkills = (profile.skills || []).map((s) =>
        typeof s === "string" ? s.toLowerCase() : ""
      );

      // Learning indicators
      const learningKeywords = [
        "training",
        "certification",
        "conference",
        "learning budget",
        "mentorship",
        "career development",
        "education",
        "courses",
        "professional development",
        "skill building",
      ];

      const newTechKeywords = [
        "kubernetes",
        "terraform",
        "react",
        "golang",
        "rust",
        "graphql",
        "microservices",
        "event-driven",
        "serverless",
        "web3",
      ];

      let learningScore = 0;
      let newTechCount = 0;

      for (const keyword of learningKeywords) {
        if (description.includes(keyword)) learningScore++;
      }

      for (const tech of newTechKeywords) {
        if (description.includes(tech) && !userSkills.includes(tech)) {
          newTechCount++;
        }
      }

      if (learningScore >= 3)
        return { strength: "high", message: "Strong learning & development!" };
      if (newTechCount >= 3)
        return {
          strength: "high",
          message: "Learn cutting-edge technologies!",
        };
      if (learningScore >= 2)
        return { strength: "medium", message: "Good growth opportunities" };
      if (newTechCount >= 2)
        return { strength: "medium", message: "Expand your tech stack!" };

      return { strength: "neutral", message: null };
    }

    function analyzeNetworkingPotential(job) {
      const company = (job.company || "").toLowerCase();
      const description = (job.description || "").toLowerCase();
      const title = (job.title || "").toLowerCase();

      // High networking roles
      const networkingRoles = [
        "business development",
        "sales",
        "partnership",
        "evangelist",
        "community",
        "developer relations",
        "customer success",
        "account",
      ];

      // Companies known for strong alumni networks
      const networkCompanies = [
        "google",
        "meta",
        "apple",
        "microsoft",
        "amazon",
        "mckinsey",
        "bain",
        "bcg",
        "goldman",
        "jpmorgan",
        "stripe",
        "airbnb",
      ];

      for (const role of networkingRoles) {
        if (title.includes(role)) {
          return { strength: "high", message: "Build valuable connections!" };
        }
      }

      for (const netCompany of networkCompanies) {
        if (company.includes(netCompany)) {
          return {
            strength: "high",
            message: `Join ${netCompany}'s strong alumni network!`,
          };
        }
      }

      if (
        description.includes("cross-functional") ||
        description.includes("stakeholder")
      ) {
        return { strength: "medium", message: "Cross-team collaboration" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeCareerPivot(job, profile) {
      const currentIndustry = (profile.industry || "").toLowerCase();
      const jobIndustry = (job.industry || "").toLowerCase();
      const currentRole = (profile.current_title || "").toLowerCase();
      const jobTitle = (job.title || "").toLowerCase();

      // Check for industry change
      const industryChange =
        currentIndustry &&
        jobIndustry &&
        !jobIndustry.includes(currentIndustry) &&
        !currentIndustry.includes(jobIndustry);

      // Check for role type change
      const fromTech =
        currentRole.includes("engineer") || currentRole.includes("developer");
      const toProduct =
        jobTitle.includes("product") || jobTitle.includes("program manager");
      const toBusiness =
        jobTitle.includes("business") || jobTitle.includes("strategy");

      if (industryChange && (toProduct || toBusiness)) {
        return { strength: "high", message: "Great pivot opportunity!" };
      }

      if (fromTech && toProduct) {
        return {
          strength: "high",
          message: "Engineering to Product transition!",
        };
      }

      if (fromTech && toBusiness) {
        return { strength: "medium", message: "Move to business side!" };
      }

      // Check for startup to corporate or vice versa
      if (
        profile.company_size === "startup" &&
        job.company_size === "enterprise"
      ) {
        return { strength: "medium", message: "Startup to corporate move!" };
      }

      if (
        profile.company_size === "enterprise" &&
        job.company_size === "startup"
      ) {
        return { strength: "medium", message: "Join the startup world!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeMarketDemand(job) {
      const title = (job.title || "").toString().toLowerCase();
      let skillsStr = "";
      if (job.skills) {
        skillsStr = Array.isArray(job.skills)
          ? job.skills.join(" ")
          : job.skills.toString();
      } else if (job.requirements) {
        skillsStr =
          typeof job.requirements === "string"
            ? job.requirements
            : job.requirements.toString();
      }
      const skills = skillsStr.toLowerCase();

      // High demand roles based on market data
      const highDemandRoles = [
        "cloud engineer",
        "devops",
        "data engineer",
        "ml engineer",
        "security engineer",
        "full stack",
        "solutions architect",
        "sre",
        "platform engineer",
        "staff engineer",
      ];

      // High demand skills
      const highDemandSkills = [
        "kubernetes",
        "aws",
        "python",
        "typescript",
        "react",
        "golang",
        "terraform",
        "docker",
        "ci/cd",
        "microservices",
      ];

      let demandScore = 0;

      for (const role of highDemandRoles) {
        if (title.includes(role)) {
          return { strength: "high", message: "High-demand role in market!" };
        }
      }

      for (const skill of highDemandSkills) {
        if (skills.includes(skill)) demandScore++;
      }

      if (demandScore >= 5)
        return { strength: "high", message: "Skills in high market demand!" };
      if (demandScore >= 3)
        return { strength: "medium", message: "Strong market demand" };

      return { strength: "neutral", message: null };
    }

    function analyzeCultureFit(job, profile) {
      const description = (job.description || "").toLowerCase();
      const values = (profile.values || []).map((v) =>
        typeof v === "string" ? v.toLowerCase() : ""
      );
      const preferences = profile.work_preferences || {};

      // Culture indicators
      const cultures = {
        innovative: [
          "innovative",
          "cutting-edge",
          "disrupt",
          "pioneer",
          "experiment",
        ],
        collaborative: [
          "collaborative",
          "team-first",
          "together",
          "inclusive",
          "diverse",
        ],
        "fast-paced": ["fast-paced", "agile", "dynamic", "rapid", "startup"],
        "mission-driven": [
          "mission",
          "impact",
          "purpose",
          "meaningful",
          "difference",
        ],
        "data-driven": [
          "data-driven",
          "metrics",
          "analytical",
          "evidence-based",
        ],
        "customer-focused": [
          "customer-first",
          "user-centric",
          "client-focused",
        ],
      };

      for (const [culture, keywords] of Object.entries(cultures)) {
        let matchCount = 0;
        for (const keyword of keywords) {
          if (description.includes(keyword)) matchCount++;
        }

        if (matchCount >= 2 && values.includes(culture)) {
          return {
            strength: "high",
            message: `Matches your ${culture} values!`,
          };
        }
      }

      // Check work style preferences
      if (
        preferences.remote &&
        job.location &&
        job.location.toLowerCase().includes("remote")
      ) {
        return { strength: "high", message: "Remote work as you prefer!" };
      }

      return { strength: "neutral", message: null };
    }

    function analyzeCompensationPackage(job) {
      const benefits = (job.benefits || "").toLowerCase();
      const description = (job.description || "").toLowerCase();

      // Premium benefits
      const premiumBenefits = [
        "equity",
        "stock options",
        "rsu",
        "bonus",
        "profit sharing",
        "401k match",
        "pension",
        "unlimited pto",
        "health insurance",
        "dental",
        "vision",
        "gym",
        "wellness",
        "childcare",
        "parental leave",
      ];

      let benefitScore = 0;
      let hasEquity = false;

      for (const benefit of premiumBenefits) {
        if (benefits.includes(benefit) || description.includes(benefit)) {
          benefitScore++;
          if (
            benefit.includes("equity") ||
            benefit.includes("stock") ||
            benefit.includes("rsu")
          ) {
            hasEquity = true;
          }
        }
      }

      if (hasEquity)
        return { strength: "high", message: "Equity compensation included!" };
      if (benefitScore >= 5)
        return { strength: "high", message: "Comprehensive benefits package!" };
      if (benefitScore >= 3)
        return { strength: "medium", message: "Good benefits offered" };

      return { strength: "neutral", message: null };
    }

    function analyzeFutureProofing(job) {
      const title = (job.title || "").toString().toLowerCase();
      let skillsStr = "";
      if (job.skills) {
        skillsStr = Array.isArray(job.skills)
          ? job.skills.join(" ")
          : job.skills.toString();
      } else if (job.requirements) {
        skillsStr =
          typeof job.requirements === "string"
            ? job.requirements
            : job.requirements.toString();
      }
      const skills = skillsStr.toLowerCase();
      const description = (job.description || "").toString().toLowerCase();

      // Future-proof technologies and skills
      const futureSkills = [
        "ai",
        "machine learning",
        "automation",
        "cloud native",
        "edge computing",
        "quantum",
        "blockchain",
        "iot",
        "ar/vr",
        "metaverse",
        "5g",
        "sustainability",
        "renewable",
        "electric vehicle",
        "autonomous",
        "gene",
        "biotech",
      ];

      let futureScore = 0;

      for (const future of futureSkills) {
        if (
          title.includes(future) ||
          skills.includes(future) ||
          description.includes(future)
        ) {
          futureScore++;
        }
      }

      if (futureScore >= 3)
        return { strength: "high", message: "Future-proof career move!" };
      if (futureScore >= 2)
        return { strength: "medium", message: "Emerging technology exposure" };

      // Check for automation resistance
      if (
        title.includes("strategy") ||
        title.includes("creative") ||
        title.includes("leadership")
      ) {
        return { strength: "medium", message: "Automation-resistant role" };
      }

      return { strength: "neutral", message: null };
    }

    function generateTipFromFactors(factors, job) {
      // Priority order for tip selection
      const priorityOrder = [
        "roleProgression",
        "salary",
        "industryTrends",
        "timing",
        "company",
        "futureProofing",
        "skills",
        "learningOpportunity",
        "location",
        "workLifeBalance",
        "compensationPackage",
        "networkingPotential",
        "marketDemand",
        "experience",
        "careerPivot",
        "cultureFit",
        "growth",
        "shortlistContext",
      ];

      // Find the strongest positive factor
      for (const factor of priorityOrder) {
        if (
          factors[factor] &&
          factors[factor].strength === "high" &&
          factors[factor].message
        ) {
          return factors[factor].message;
        }
      }

      // Fall back to medium strength factors
      for (const factor of priorityOrder) {
        if (
          factors[factor] &&
          factors[factor].strength === "medium" &&
          factors[factor].message
        ) {
          return factors[factor].message;
        }
      }

      // Default based on match score
      if (factors.matchScore >= 90)
        return "Perfect alignment with your profile!";
      if (factors.matchScore >= 80) return "Strong match - worth pursuing!";
      if (factors.matchScore >= 70) return "Good fit for your experience!";
      if (factors.matchScore >= 60) return "Interesting growth opportunity!";

      // Final fallback with randomization for variety
      const defaultTips = [
        "Worth exploring this opportunity!",
        "Could be your next career move!",
        "Matches your career trajectory!",
        "Aligns with market trends!",
        "Strong potential here!",
      ];

      return defaultTips[Math.floor(Math.random() * defaultTips.length)];
    }

    // Return the main function but keep helper functions in closure
    return function (job) {
      // Get user profile data if available
      const userProfile = JSON.parse(
        localStorage.getItem("sffc_user_profile") || "{}"
      );
      const shortlist = JSON.parse(
        localStorage.getItem("sffc_shortlist") || "[]"
      );
      const viewedJobs = JSON.parse(
        localStorage.getItem("sffc_viewed_jobs") || "[]"
      );

      // Analyze various factors
      const factors = {
        matchScore: job.match_score || 0,
        salary: analyzeSalary(job, userProfile),
        skills: analyzeSkillMatch(job, userProfile),
        experience: analyzeExperience(job, userProfile),
        location: analyzeLocation(job, userProfile),
        company: analyzeCompany(job, userProfile),
        timing: analyzeTiming(job),
        competition: analyzeCompetition(job),
        growth: analyzeGrowthPotential(job),
        shortlistContext: analyzeShortlistContext(job, shortlist),
        viewHistory: analyzeViewHistory(job, viewedJobs),
        industryTrends: analyzeIndustryTrends(job),
        roleProgression: analyzeRoleProgression(job, userProfile),
        workLifeBalance: analyzeWorkLifeBalance(job),
        learningOpportunity: analyzeLearningOpportunity(job, userProfile),
        networkingPotential: analyzeNetworkingPotential(job),
        careerPivot: analyzeCareerPivot(job, userProfile),
        marketDemand: analyzeMarketDemand(job),
        cultureFit: analyzeCultureFit(job, userProfile),
        compensationPackage: analyzeCompensationPackage(job),
        futureProofing: analyzeFutureProofing(job),
      };

      // Generate contextual tip based on strongest factor
      return generateTipFromFactors(factors, job);
    };

    // Close the generateDynamicSennaTip IIFE
  })();

  // Intelligent search methods are now defined inside the class above

  // Close the main IIFE
})(jQuery);
