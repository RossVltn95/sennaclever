/**
 * Action Cards System - Core Infrastructure
 * Transforms question cards into AI action triggers in the PE filter sidebar
 */

(function ($) {
  "use strict";

  // Immediately prevent other systems from initializing
  window.peFilterCardsSystem = null;
  window.PEFilterCardsSystem = null;

  // Override the constructors to prevent new instances
  Object.defineProperty(window, "peFilterCardsSystem", {
    set: function (value) {
      console.log("Blocked peFilterCardsSystem initialization");
      return null;
    },
    get: function () {
      return null;
    },
  });

  Object.defineProperty(window, "PEFilterCardsSystem", {
    set: function (value) {
      console.log("Blocked PEFilterCardsSystem initialization");
      return null;
    },
    get: function () {
      return null;
    },
  });

  class ActionCardsSystem {
    constructor() {
      this.initialized = false;
      this.currentFilter = "quick-actions";
      this.userContext = {};
      this.actionCards = [];
      this.loadActionCards();
      this.init();
    }

    init() {
      // Wait for DOM and MENA Careers to be ready
      $(document).ready(() => {
        console.log("Action Cards System: Waiting for containers...");

        // Ensure SennaChat is loaded
        this.waitForSennaChat();

        // Wait a bit for pe-filter-sidebar to be created
        const initInterval = setInterval(() => {
          // Look for multiple possible containers
          const sidebar = $(".pe-filter-sidebar, #pe-filter-container").first();
          const quickFilters = $(".pe-quick-filters");
          const mainFilters = $(".pe-main-filters");

          // Log what we're finding
          console.log("Action Cards: Checking containers...", {
            sidebar: sidebar.length,
            quickFilters: quickFilters.length,
            mainFilters: mainFilters.length,
          });

          // We need at least the sidebar or main container
          if (sidebar.length || mainFilters.length) {
            clearInterval(initInterval);
            console.log(
              "Action Cards System: Found containers, initializing..."
            );

            // If quick filters don't exist yet, wait for pe-filters.js to create them
            if (!quickFilters.length) {
              console.log(
                "Quick filters not ready, waiting for pe-filters.js..."
              );
              setTimeout(() => {
                this.completeInitialization();
              }, 1000);
            } else {
              this.completeInitialization();
            }
          }
        }, 500);

        // Timeout after 15 seconds (increased from 10)
        setTimeout(() => {
          clearInterval(initInterval);
          if (!this.initialized) {
            console.error(
              "Action Cards System: Could not find container after 15 seconds"
            );
            // Try one more time
            this.completeInitialization();
          }
        }, 15000);
      });
    }

    waitForSennaChat() {
      let attempts = 0;
      const maxAttempts = 30; // 15 seconds

      const checkInterval = setInterval(() => {
        attempts++;

        if (window.SennaChat && typeof window.SennaChat.send === "function") {
          console.log("✓ SennaChat is available");
          clearInterval(checkInterval);
        } else if (attempts >= maxAttempts) {
          console.warn(
            "⚠ SennaChat not available after 15 seconds, will use fallbacks"
          );
          clearInterval(checkInterval);
        } else if (attempts % 10 === 0) {
          console.log(`Waiting for SennaChat... (${attempts * 0.5}s)`);
        }
      }, 500);
    }

    completeInitialization() {
      console.log("Action Cards System: Starting complete initialization...");

      // Clear any existing cards from other systems
      $(".pe-main-filters").empty();

      this.detectUserContext();
      this.addPromptLibraryFilter();
      this.setupFilterTabs();
      this.renderCards();
      this.bindEventHandlers();

      // Show action cards by default since Prompt Library is active
      this.showActionCards();

      // Ensure Prompt Library stays active
      this.ensurePromptLibraryActive();

      this.initialized = true;
      console.log("Action Cards System initialized successfully");
    }

    /**
     * Ensure Prompt Library filter stays active
     */
    ensurePromptLibraryActive() {
      // Keep checking that our filter is active
      setInterval(() => {
        const promptFilter = $(
          '.pe-quick-filter-item[data-quick="prompt-library"]'
        );
        if (promptFilter.length && !promptFilter.hasClass("active")) {
          console.log("Reactivating Prompt Library filter");
          $(".pe-quick-filter-item").removeClass("active");
          promptFilter.addClass("active");
          this.showActionCards();
        }
      }, 1000);
    }

    /**
     * Add Prompt Library filter to quick filters bar
     */
    addPromptLibraryFilter() {
      const quickFilters = $(".pe-quick-filters");
      if (!quickFilters.length) {
        console.log("Action Cards: No quick filters bar found");
        return;
      }

      // Check if Prompt Library filter already exists
      if (quickFilters.find('[data-quick="prompt-library"]').length) {
        return;
      }

      // Remove active class from all filters
      quickFilters.find(".pe-quick-filter-item").removeClass("active");

      // Add Prompt Library as the first filter and make it active
      const promptLibraryFilter = `
                <div class="pe-quick-filter-item active" data-quick="prompt-library">
                    <div class="pe-quick-icon">
                        <div class="pe-quick-icon-inner">AI</div>
                    </div>
                    <span class="pe-filter-label">Prompt Library</span>
                </div>
            `;

      // Insert as first item
      quickFilters.prepend(promptLibraryFilter);

      // Bind click handler for Prompt Library
      quickFilters.find('[data-quick="prompt-library"]').on("click", (e) => {
        e.preventDefault();
        e.stopPropagation();

        // Remove active from all
        quickFilters.find(".pe-quick-filter-item").removeClass("active");
        // Add active to this
        $(e.currentTarget).addClass("active");

        // Show action cards
        this.showActionCards();
      });

      // Update other filter clicks to hide action cards
      quickFilters
        .find('.pe-quick-filter-item:not([data-quick="prompt-library"])')
        .on("click", (e) => {
          // Hide action cards when other filters are clicked
          this.hideActionCards();
        });

      console.log("Action Cards: Prompt Library filter added");
    }

    /**
     * Show action cards and hide old system
     */
    showActionCards() {
      // Hide old cards if they exist
      $(".question-card:not(.action-card)").hide();
      $(".content-scroll > div:not(.action-cards-container)").hide();

      // Show filter tabs
      $(".action-filter-tabs").show();

      // Show action cards container
      $(".action-cards-container").show();

      // Re-render cards
      this.renderCards();
    }

    /**
     * Hide action cards and show old system
     */
    hideActionCards() {
      // Hide action cards
      $(".action-filter-tabs").hide();
      $(".action-cards-container").hide();

      // Show old cards
      $(".question-card:not(.action-card)").show();
      $(".content-scroll > div:not(.action-cards-container)").show();
    }

    /**
     * Detect user context for smart card display
     */
    detectUserContext() {
      const now = new Date();

      this.userContext = {
        hasCVUploaded:
          localStorage.getItem("sffc_cv_uploaded") === "true" ||
          window.currentCVContent !== undefined ||
          $(".cv-upload-success").length > 0,
        hasViewedJobs:
          (window.displayedJobIds && window.displayedJobIds.size > 0) ||
          $(".sffc-match-card").length > 0,
        savedJobsCount: JSON.parse(
          localStorage.getItem("sffc_saved_jobs") || "[]"
        ).length,
        trackedJobsCount: JSON.parse(
          localStorage.getItem("sffc_tracked_jobs") || "[]"
        ).length,
        currentDay: now.getDay(), // 0 = Sunday, 1 = Monday, etc.
        currentHour: now.getHours(),
        currentMonth: now.getMonth() + 1,
        isWeekend: now.getDay() === 0 || now.getDay() === 6,
        isMorning: now.getHours() >= 6 && now.getHours() < 12,
        isEvening: now.getHours() >= 17 && now.getHours() < 22,
        isBusinessHours:
          now.getHours() >= 9 &&
          now.getHours() < 18 &&
          now.getDay() >= 1 &&
          now.getDay() <= 5,
        lastJobViewed: window.currentTailoringJob || null,
        hasInteracted: localStorage.getItem("sffc_user_interacted") === "true",
        isNewUser: !localStorage.getItem("sffc_returning_user"),
        sessionDuration: this.getSessionDuration(),
      };

      // Mark user as having interacted
      localStorage.setItem("sffc_user_interacted", "true");
      localStorage.setItem("sffc_returning_user", "true");

      console.log("User context detected:", this.userContext);
    }

    getSessionDuration() {
      const sessionStart = sessionStorage.getItem("sffc_session_start");
      if (!sessionStart) {
        sessionStorage.setItem("sffc_session_start", Date.now());
        return 0;
      }
      return Math.floor((Date.now() - parseInt(sessionStart)) / 60000); // minutes
    }

    /**
     * Load all action cards data - Complete 70 cards implementation
     */
    loadActionCards() {
      this.actionCards = [
  // LBO MODELING (8 cards)
  {
    id: "lbo-fundamentals",
    category: "quick-actions",
    badge: "→",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "LBO Fundamentals",
    preview: "Learn what makes a leveraged buyout work and why PE firms use them.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me the fundamentals of leveraged buyouts - what they are, why PE firms use them, and how leverage magnifies returns",
    requiresCV: false,
    priority: 1,
  },
  {
    id: "lbo-target-screening",
    category: "quick-actions",
    badge: "◎",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "LBO Target Screening",
    preview: "Master how to identify and evaluate the best LBO candidates.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to screen and identify attractive LBO targets - what metrics to look for, red flags to avoid, and how to evaluate companies",
    requiresCV: false,
    priority: 2,
  },
  {
    id: "debt-structures",
    category: "quick-actions",
    badge: "▨",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Debt Structures & Tranches",
    preview: "Understand different debt types used in LBOs and their purposes.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me about debt structures in LBOs - senior debt, mezzanine, PIK notes, and how to optimize the capital structure",
    requiresCV: false,
    priority: 3,
  },
  {
    id: "sources-uses",
    category: "quick-actions",
    badge: "◈",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Sources & Uses Tables",
    preview: "Learn how to build sources and uses tables for LBO transactions.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to construct a sources and uses table for an LBO transaction",
    requiresCV: false,
    priority: 4,
  },
  {
    id: "returns-analysis",
    category: "quick-actions",
    badge: "▲",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Returns Analysis (IRR & MOIC)",
    preview: "Calculate and interpret IRR and MOIC for LBO investments.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to calculate and analyze IRR and MOIC (multiple on invested capital) for LBO deals",
    requiresCV: false,
    priority: 5,
  },
  {
    id: "exit-strategies",
    category: "quick-actions",
    badge: "✎",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Exit Strategies & Timing",
    preview: "Learn different exit options and how to maximize returns at exit.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me about exit strategies for PE investments - IPOs, strategic sales, secondary buyouts, and how to time exits",
    requiresCV: false,
    priority: 6,
  },
  {
    id: "sensitivity-analysis",
    category: "quick-actions",
    badge: "◉",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Sensitivity & Scenario Analysis",
    preview: "Build sensitivity tables to stress-test LBO models.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to build sensitivity and scenario analysis for LBO models to test different assumptions",
    requiresCV: false,
    priority: 7,
  },
  {
    id: "management-incentives",
    category: "quick-actions",
    badge: "★",
    badgeText: "Learn",
    categoryLabel: "LBO MODELING",
    title: "Management Incentives",
    preview: "Understand how to structure management equity and incentive packages.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how PE firms structure management incentive packages and rollover equity in LBOs",
    requiresCV: false,
    priority: 8,
  },

  // DEAL SOURCING (6 cards)
  {
    id: "proprietary-deal-flow",
    category: "quick-actions",
    badge: "▬",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Proprietary Deal Flow",
    preview: "Learn how to source deals off-market and build relationships.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how PE firms generate proprietary deal flow and source off-market opportunities",
    requiresCV: false,
    priority: 9,
  },
  {
    id: "industry-mapping",
    category: "quick-actions",
    badge: "$",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Industry Mapping",
    preview: "Master how to map industries and identify potential targets.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to map an industry sector and identify attractive acquisition targets systematically",
    requiresCV: false,
    priority: 10,
  },
  {
    id: "intermediary-relationships",
    category: "quick-actions",
    badge: "→",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Investment Banker Relationships",
    preview: "Learn how to build and leverage relationships with investment bankers.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how PE firms build relationships with investment bankers and intermediaries to access deal flow",
    requiresCV: false,
    priority: 11,
  },
  {
    id: "buy-and-build",
    category: "quick-actions",
    badge: "▢",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Buy-and-Build Strategies",
    preview: "Understand how to identify platform companies and add-on acquisitions.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me about buy-and-build strategies in PE - how to identify platform companies and execute add-on acquisitions",
    requiresCV: false,
    priority: 12,
  },
  {
    id: "auction-processes",
    category: "quick-actions",
    badge: "⥝",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Competitive Auction Processes",
    preview: "Learn how to navigate and win competitive sale processes.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how competitive auction processes work and how PE firms position themselves to win deals",
    requiresCV: false,
    priority: 13,
  },
  {
    id: "deal-screening",
    category: "quick-actions",
    badge: "◔",
    badgeText: "Learn",
    categoryLabel: "DEAL SOURCING",
    title: "Initial Deal Screening",
    preview: "Master the first-pass screening criteria for potential deals.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to quickly screen deals and determine which opportunities to pursue further",
    requiresCV: false,
    priority: 14,
  },

  // DUE DILIGENCE (7 cards)
  {
    id: "financial-dd",
    category: "quick-actions",
    badge: "▣",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Financial Due Diligence",
    preview: "Learn how to conduct thorough financial due diligence.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to conduct financial due diligence for PE deals - what to look for, red flags, and quality of earnings analysis",
    requiresCV: false,
    priority: 15,
  },
  {
    id: "commercial-dd",
    category: "quick-actions",
    badge: "◈",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Commercial Due Diligence",
    preview: "Understand market analysis and competitive positioning assessment.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to conduct commercial due diligence - market sizing, competitive analysis, and customer validation",
    requiresCV: false,
    priority: 16,
  },
  {
    id: "operational-dd",
    category: "quick-actions",
    badge: "▨",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Operational Due Diligence",
    preview: "Assess operational capabilities and improvement opportunities.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess operational capabilities and identify improvement opportunities in target companies",
    requiresCV: false,
    priority: 17,
  },
  {
    id: "legal-dd",
    category: "quick-actions",
    badge: "→",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Legal Due Diligence",
    preview: "Understand key legal issues and risks in PE transactions.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me about legal due diligence in PE deals - contracts, litigation, IP, and regulatory issues",
    requiresCV: false,
    priority: 18,
  },
  {
    id: "management-assessment",
    category: "quick-actions",
    badge: "◎",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Management Team Assessment",
    preview: "Learn how to evaluate and validate management capabilities.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess management teams during due diligence and identify talent gaps",
    requiresCV: false,
    priority: 19,
  },
  {
    id: "quality-of-earnings",
    category: "quick-actions",
    badge: "▲",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Quality of Earnings Analysis",
    preview: "Master how to normalize EBITDA and assess earnings quality.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to conduct quality of earnings analysis and normalize EBITDA for PE deals",
    requiresCV: false,
    priority: 20,
  },
  {
    id: "working-capital-analysis",
    category: "quick-actions",
    badge: "✎",
    badgeText: "Learn",
    categoryLabel: "DUE DILIGENCE",
    title: "Working Capital Analysis",
    preview: "Understand working capital requirements and cash conversion cycles.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to analyze working capital requirements and assess cash conversion efficiency",
    requiresCV: false,
    priority: 21,
  },

  // VALUE CREATION (8 cards)
  {
    id: "100-day-plan",
    category: "quick-actions",
    badge: "◉",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "100-Day Plan Development",
    preview: "Learn how to build an effective 100-day plan post-acquisition.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to develop a comprehensive 100-day plan for a newly acquired portfolio company",
    requiresCV: false,
    priority: 22,
  },
  {
    id: "revenue-growth-initiatives",
    category: "quick-actions",
    badge: "★",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Revenue Growth Initiatives",
    preview: "Identify and execute revenue growth opportunities.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to identify and prioritize revenue growth initiatives in portfolio companies",
    requiresCV: false,
    priority: 23,
  },
  {
    id: "operational-improvements",
    category: "quick-actions",
    badge: "▬",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Operational Improvements",
    preview: "Drive margin expansion through operational excellence.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to identify and implement operational improvements to expand margins in portfolio companies",
    requiresCV: false,
    priority: 24,
  },
  {
    id: "pricing-optimization",
    category: "quick-actions",
    badge: "$",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Pricing Optimization",
    preview: "Master pricing strategies to improve profitability.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to analyze and optimize pricing strategies in portfolio companies",
    requiresCV: false,
    priority: 25,
  },
  {
    id: "add-on-acquisitions",
    category: "quick-actions",
    badge: "→",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Add-On Acquisition Strategy",
    preview: "Learn how to identify and integrate bolt-on acquisitions.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to source, evaluate, and integrate add-on acquisitions to build platform value",
    requiresCV: false,
    priority: 26,
  },
  {
    id: "kpi-dashboards",
    category: "quick-actions",
    badge: "▢",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "KPI Dashboard Design",
    preview: "Build effective KPI dashboards to monitor portfolio performance.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to design KPI dashboards and reporting systems for portfolio company monitoring",
    requiresCV: false,
    priority: 27,
  },
  {
    id: "sales-force-effectiveness",
    category: "quick-actions",
    badge: "⥝",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Sales Force Effectiveness",
    preview: "Improve sales team productivity and results.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess and improve sales force effectiveness in portfolio companies",
    requiresCV: false,
    priority: 28,
  },
  {
    id: "digital-transformation",
    category: "quick-actions",
    badge: "◔",
    badgeText: "Learn",
    categoryLabel: "VALUE CREATION",
    title: "Digital Transformation",
    preview: "Drive digital initiatives to modernize portfolio companies.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess digital maturity and prioritize digital transformation initiatives",
    requiresCV: false,
    priority: 29,
  },

  // PORTFOLIO MANAGEMENT (6 cards)
  {
    id: "board-governance",
    category: "quick-actions",
    badge: "▣",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "Board Governance & Oversight",
    preview: "Learn effective board governance for portfolio companies.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me about board governance best practices and how to be an effective board member",
    requiresCV: false,
    priority: 30,
  },
  {
    id: "portfolio-monitoring",
    category: "quick-actions",
    badge: "◈",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "Portfolio Monitoring Systems",
    preview: "Set up systems to track portfolio company performance.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to set up portfolio monitoring systems and track key value drivers",
    requiresCV: false,
    priority: 31,
  },
  {
    id: "talent-management",
    category: "quick-actions",
    badge: "▨",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "Executive Talent Management",
    preview: "Recruit, retain, and develop executive talent.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess, recruit, and develop executive talent in portfolio companies",
    requiresCV: false,
    priority: 32,
  },
  {
    id: "crisis-management",
    category: "quick-actions",
    badge: "→",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "Crisis Management",
    preview: "Navigate challenges and turnarounds in portfolio companies.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to manage crises and execute turnarounds in underperforming portfolio companies",
    requiresCV: false,
    priority: 33,
  },
  {
    id: "exit-prep",
    category: "quick-actions",
    badge: "◎",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "Exit Preparation",
    preview: "Prepare portfolio companies for successful exits.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to prepare portfolio companies for exit - positioning, timing, and value maximization",
    requiresCV: false,
    priority: 34,
  },
  {
    id: "esg-integration",
    category: "quick-actions",
    badge: "▲",
    badgeText: "Learn",
    categoryLabel: "PORTFOLIO MANAGEMENT",
    title: "ESG Integration",
    preview: "Integrate ESG considerations into portfolio management.",
    buttonText: "Start Learning",
    actionType: "learn",
    prompt: "Teach me how to assess and improve ESG practices in portfolio companies",
    requiresCV: false,
    priority: 35,
  },
];
    }

    /**
     * Setup filter tabs in sidebar
     */
    setupFilterTabs() {
      const sidebar = $(".pe-filter-sidebar");
      if (!sidebar.length) {
        console.log("Action Cards: No sidebar found for tabs");
        return;
      }

      // Check if tabs already exist
      if (!$(".action-filter-tabs").length) {
        const tabsHTML = `
                    <div class="action-filter-tabs">
                        <button class="filter-tab active" data-filter="quick-actions">
                            <span class="tab-icon">→</span>
                            <span class="tab-label">All Lessons</span>
                        </button>
                    </div>
                `;

        // Insert tabs after stories bar or at the beginning
        const quickFilters = $(".pe-quick-filters");
        if (quickFilters.length) {
          quickFilters.after(tabsHTML);
        } else {
          sidebar.prepend(tabsHTML);
        }

        console.log("Action Cards: Filter tabs added");
      }
    }

    /**
     * Get relevant cards based on context and filter
     */
    getRelevantCards() {
      let cards = this.actionCards.filter((card) => {
        // Filter by current tab
        if (card.category !== this.currentFilter) {
          return false;
        }

        // Check CV requirement
        if (card.requiresCV && !this.userContext.hasCVUploaded) {
          return false;
        }

        // Check jobs requirement
        if (card.requiresJobs) {
          if (!this.userContext.hasViewedJobs) return false;
          if (card.minJobs && this.userContext.savedJobsCount < card.minJobs)
            return false;
        }

        // Check time-based cards
        if (card.showOn) {
          switch (card.showOn) {
            case "monday":
              if (this.userContext.currentDay !== 1) return false;
              break;
            case "friday":
              if (this.userContext.currentDay !== 5) return false;
              break;
            case "weekend":
              if (!this.userContext.isWeekend) return false;
              break;
            case "morning":
              if (!this.userContext.isMorning) return false;
              break;
            case "evening":
              if (!this.userContext.isEvening) return false;
              break;
          }
        }

        return true;
      });

      // Sort by priority
      cards.sort((a, b) => (a.priority || 999) - (b.priority || 999));

      // If no cards match criteria, show defaults for the category
      if (cards.length === 0) {
        cards = this.getDefaultCardsForCategory(this.currentFilter);
      }

      return cards;
    }

    /**
     * Get default cards when no context matches
     */
    getDefaultCardsForCategory(category) {
      const defaults = {
        "quick-actions": [
          "linkedin-audit",
          "pe-career-plan",
          "salary-calculator",
        ],
        "time-based": ["interview-prep"],
        "job-actions": ["interview-prep"],
        insights: ["pe-roadmap-info"],
      };

      const defaultIds = defaults[category] || [];
      return this.actionCards.filter((card) => defaultIds.includes(card.id));
    }

    /**
     * Render cards in the sidebar
     */
    renderCards() {
      // Find the correct container - try multiple selectors
      let container = $(".pe-main-filters");
      if (!container.length) {
        // Try to find it inside pe-filter-sidebar
        container = $(".pe-filter-sidebar .pe-main-filters");
      }
      if (!container.length) {
        // Try inside pe-filter-container
        container = $("#pe-filter-container .pe-main-filters");
      }
      if (!container.length) {
        // Look for any sidebar container
        const sidebar = $(".pe-filter-sidebar, #pe-filter-container").first();
        if (sidebar.length) {
          // Create pe-main-filters if it doesn't exist
          container = $('<div class="pe-main-filters"></div>');

          // Insert after quick filters if they exist
          const quickFilters = sidebar.find(".pe-quick-filters");
          if (quickFilters.length) {
            quickFilters.after(container);
          } else {
            sidebar.append(container);
          }

          console.log("Action Cards: Created pe-main-filters container");
        } else {
          console.log(
            "Action Cards: No sidebar found, creating minimal structure"
          );
          // Create minimal structure if nothing exists
          const minimalContainer = $(
            "#pe-filter-container, .pe-filter-sidebar"
          ).first();
          if (minimalContainer.length) {
            container = $('<div class="pe-main-filters"></div>');
            minimalContainer.append(container);
          } else {
            console.error("Action Cards: Cannot find any suitable container");
            return;
          }
        }
      }

      console.log("Action Cards: Rendering cards in container:", container[0]);

      const cards = this.getRelevantCards();

      // Create cards HTML
      const cardsHTML = cards
        .map((card, index) => this.createCardHTML(card, index))
        .join("");

      // Clear any existing pe-filter-cards content and replace with action cards
      container.html(`
                <div class="action-cards-container">
                    ${cardsHTML}
                </div>
            `);

      // Update active count in tab
      const activeTab = $(`.filter-tab[data-filter="${this.currentFilter}"]`);
      activeTab.find(".tab-count").remove();
      if (cards.length > 0) {
        activeTab.append(`<span class="tab-count">${cards.length}</span>`);
      }
    }

    /**
     * Create HTML for a single card
     */
    createCardHTML(card, index) {
      const gradients = [
        "linear-gradient(135deg, #0d353e 0%, #1a5a65 100%)",
        "linear-gradient(145deg, #0d353e 0%, #2a6a75 100%)",
        "linear-gradient(125deg, #0d353e 0%, #1f5460 100%)",
        "linear-gradient(155deg, #0d353e 0%, #3a7a85 100%)",
      ];

      const gradient = gradients[index % gradients.length];

      return `
                <div class="question-card action-card" 
                     data-card-id="${card.id}" 
                     data-category="${card.category}"
                     style="background: ${gradient}; min-height: 60vh; max-height: 70vh;">
                    
                    ${
                      card.badge
                        ? `
                    <div class="trending-badge">
                        <div class="trending-icon">${card.badge}</div>
                        <span class="trending-text">${card.badgeText}</span>
                    </div>
                    `
                        : ""
                    }
                    
                    <div class="question-content">
                        <div class="question-category">${
                          card.categoryLabel
                        }</div>
                        <h2 class="question-title">${card.title}</h2>
                        <p class="question-preview">${card.preview}</p>
                        
                        ${this.getCardMetadata(card)}
                    </div>
                    
                    <div class="bottom-cta">
                        <button class="action-trigger-btn ask-senna-btn" 
                                data-action-type="${card.actionType}"
                                data-prompt="${this.escapeHtml(card.prompt)}"
                                data-card-id="${card.id}"
                                title="${this.escapeHtml(card.prompt)}"
                                ${
                                  card.requiresCV
                                    ? 'data-requires-cv="true"'
                                    : ""
                                }
                                ${
                                  card.requiresJobs
                                    ? 'data-requires-jobs="true"'
                                    : ""
                                }>
                            <span>${card.buttonText}</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>
            `;
    }

    /**
     * Get metadata tags for card
     */
    getCardMetadata(card) {
      const tags = [];

      if (card.requiresCV) {
        tags.push('<span class="meta-tag">Requires CV</span>');
      }

      if (card.requiresJobs) {
        const minJobs = card.minJobs || 1;
        tags.push(`<span class="meta-tag">Needs ${minJobs}+ saved jobs</span>`);
      }

      if (card.showOn) {
        tags.push(`<span class="meta-tag time-tag">${card.showOn}</span>`);
      }

      return tags.length > 0
        ? `<div class="filter-meta">${tags.join("")}</div>`
        : "";
    }

    /**
     * Bind all event handlers
     */
    bindEventHandlers() {
      const self = this;

      // Filter tab clicks
      $(document).on("click", ".filter-tab", function (e) {
        e.preventDefault();
        const filter = $(this).data("filter");

        // Update active state
        $(".filter-tab").removeClass("active");
        $(this).addClass("active");

        // Update filter and re-render with loading state
        self.currentFilter = filter;
        self.showLoadingState();
        setTimeout(() => {
          self.renderCards();
          self.hideLoadingState();
        }, 300);
      });

      // Action trigger button clicks
      $(document).on("click", ".action-trigger-btn", function (e) {
        console.log("ACTION CARD CLICKED!", e.target);

        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const actionType = $btn.data("action-type");
        const prompt = $btn.data("prompt");
        const cardId = $btn.data("card-id");
        const requiresCV = $btn.data("requires-cv");
        const requiresJobs = $btn.data("requires-jobs");

        console.log("Button data:", {
          actionType,
          prompt: prompt?.substring(0, 50),
          cardId,
        });

        const isAskSenna = $btn.hasClass("ask-senna-btn");
        const isCVAction =
          typeof actionType === "string" && actionType.indexOf("cv-") === 0;

        const ajax = window.sffc_ajax || {};
        const frontend = window.sffc_frontend || {};
        const loggedIn = !!(
          ajax.user_logged_in ||
          ajax.is_logged_in === "1" ||
          ajax.is_logged_in === true ||
          frontend.is_logged_in === "1" ||
          frontend.is_logged_in === true ||
          parseInt(ajax.user_id || frontend.user_id || 0, 10) > 0
        );

        if (isCVAction && !loggedIn) {
          if (
            window.SkillFarmAccess &&
            typeof window.SkillFarmAccess.requireLogin === "function"
          ) {
            if (!window.SkillFarmAccess.requireLogin("cv-action-card")) {
              return;
            }
          }
        } else if (isAskSenna && !isCVAction && !loggedIn) {
          if (
            window.SkillFarmAccess &&
            typeof window.SkillFarmAccess.showPrompt === "function"
          ) {
            window.SkillFarmAccess.showPrompt("action-card", { force: true });
          }
        }

        // ALL ACTION CARDS NOW TRIGGER LEARNING EXERCISES
        // Route to learning system instead of single prompts
        console.log("[Action Cards] Routing to learning exercise system for:", cardId);

        // Trigger the learning exercise system
        if (window.peLearningExercises) {
          window.peLearningExercises.startExercise(cardId, {
            title: $btn.closest('.action-card').find('.question-title').text(),
            originalPrompt: prompt,
            actionType: actionType
          });
        } else {
          console.error("[Action Cards] PE Learning system not loaded");
        }
        return; // Don't proceed with normal action flow

        // Check requirements
        if (requiresCV && !self.userContext.hasCVUploaded) {
          self.handleMissingCV();
          return;
        }

        if (requiresJobs && !self.userContext.hasViewedJobs) {
          self.handleNoJobs();
          return;
        }

        // Show loading state
        const originalHTML = $btn.html();
        $btn.html('<span class="spinner"></span> <span>Processing...</span>');
        $btn.prop("disabled", true);

        // Trigger the action immediately
        console.log(
          "Calling triggerAction with:",
          actionType,
          prompt?.substring(0, 50)
        );
        self.triggerAction(actionType, prompt, cardId, {
          isAskSenna,
          isCVAction,
        });

        // Restore button after delay
        setTimeout(() => {
          $btn.html(originalHTML);
          $btn.prop("disabled", false);
        }, 1500);
      });

      // Refresh context periodically (every minute)
      setInterval(() => {
        const oldContext = JSON.stringify(self.userContext);
        self.detectUserContext();
        const newContext = JSON.stringify(self.userContext);

        // Re-render if context changed
        if (oldContext !== newContext) {
          console.log("Context changed, refreshing cards");
          self.renderCards();
        }
      }, 60000);
    }

    /**
     * Check if action type should open visual artifact
     */
    shouldOpenArtifact(actionType) {
      const artifactEnabledTypes = [
        "cv-tailor",
        "cv-analyze",
        "cv-optimize",
        "cv-enhance",
        "analyze",
        "assess",
        "evaluate",
        "plan",
        "strategy",
        "map",
        "search",
        "research",
        "explore",
        "prepare",
        "guide",
        "develop",
        "negotiate",
        "calculate",
        "build",
        "create",
        "generate",
        "audit",
        "highlight",
        "craft", // LinkedIn & Profile
        "respond",
        "request", // Communication
      ];

      return artifactEnabledTypes.includes(actionType);
    }

    /**
     * Trigger action in MENA Careers chat
     */
    triggerAction(actionType, prompt, cardId, meta = {}) {
      console.log("triggerAction called with:", {
        actionType,
        prompt: prompt?.substring(0, 50),
        cardId,
      });

      const isAskSenna = !!meta.isAskSenna;
      const isCVAction = !!meta.isCVAction;

      // -------------------------------
      // 🚨 Restrict logged-out users to 3 messages
      // -------------------------------
      const ajax = window.sffc_ajax || {};
      const frontend = window.sffc_frontend || {};
      const loggedIn = !!(
        ajax.user_logged_in ||
        ajax.is_logged_in === "1" ||
        ajax.is_logged_in === true ||
        frontend.is_logged_in === "1" ||
        frontend.is_logged_in === true ||
        parseInt(ajax.user_id || frontend.user_id || 0, 10) > 0
      );

      if (!loggedIn) {
        let usage = parseInt(
          localStorage.getItem("sffc_guest_usage") || "0",
          10
        );

        if (usage >= 1) {
          console.warn("Guest usage limit reached, showing join prompt");

          // Insert your pre-styled join dialogue
          const $dialog = $(`
              <div class="sffc-message sffc-message-senna senna-join-prompt">
                You’ve reached your free limit. Please join to continue using the AI assistant.
              </div>
            `);

          $(".sffc-senna-conversation").append($dialog);

          return; // 🚫 stop execution, don’t send the prompt
        } else {
          usage++;
          localStorage.setItem("sffc_guest_usage", usage);
          console.log(`Guest usage count: ${usage}/1`);
        }
      }
      // -------------------------------

      // Check if this action type should open a visual artifact (inline version)
      const shouldSkipArtifacts = isAskSenna && !isCVAction;

      if (
        !shouldSkipArtifacts &&
        window.visualArtifactsInline &&
        this.shouldOpenArtifact(actionType)
      ) {
        // Trigger the inline visual artifact system
        $(document).trigger("action-card-triggered", {
          actionType: actionType,
          prompt: prompt,
          cardId: cardId,
        });
        return;
      }

      // Default behavior: send directly to chat as general AI query
      // Try to use processGeneralQuery to bypass job filtering
      if (window.SennaChat && window.SennaChat.processGeneralQuery) {
        console.log("Using processGeneralQuery to bypass job filtering");
        window.SennaChat.processGeneralQuery(prompt);
      } else if (window.sennaChat && window.sennaChat.processGeneralQuery) {
        console.log("Using sennaChat.processGeneralQuery");
        window.sennaChat.processGeneralQuery(prompt);
      } else {
        // Fallback: Put the prompt in the input field and send using robust method
        const $input = $("#senna-input");
        const $sendBtn = $(
          "#senna-send, .sffc-send-btn, button[type='submit']"
        ).first();

        if ($input.length && $sendBtn.length) {
          console.log("Using robust input + button method");

          // Set the value and trigger framework events
          $input.val(prompt).trigger("input").trigger("change");

          // Delay click to give UI time to register
          setTimeout(() => {
            $sendBtn.trigger("click");

            // Fallback: double-click if still not processed
            setTimeout(() => {
              if ($input.val() === prompt) {
                $sendBtn.trigger("click");
              }
            }, 200);
          }, 100);
        } else {
          console.error("Could not find input or send button");
        }
      }

      // Step 3: On mobile, show the chat UI
      if (window.innerWidth <= 768) {
        console.log("Mobile detected, switching UI...");

        // Show the chat conversation
        const $chatConv = $(".sffc-senna-conversation");
        console.log(
          "Chat container found:",
          $chatConv.length > 0 ? "✅" : "❌"
        );
        if ($chatConv.length) {
          $chatConv.show().css({
            display: "flex",
            visibility: "visible",
            opacity: "1",
            position: "relative",
            "z-index": "9999",
          });
          console.log("Chat container shown");
        }

        // Hide the cards/filter sidebar
        const $sidebar = $(".pe-filter-sidebar, .pe-main-filters");
        console.log("Hiding sidebar:", $sidebar.length > 0 ? "✅" : "❌");
        $sidebar.hide();

        // If there's a mobile-specific chat container, show it
        const $mobileChat = $(".mobile-senna-conversation");
        if ($mobileChat.length) {
          console.log("Mobile chat container found, showing...");
          $mobileChat.show().css({
            display: "flex",
            visibility: "visible",
            opacity: "1",
          });
        }

        // Update any mode indicators
        $(".mode-pill").removeClass("active");
        $('.mode-pill[data-mode="chat"]').addClass("active");

        // Make sure body knows we're in chat mode
        $("body").addClass("mobile-chat-active");
        console.log("Mobile UI switch complete");
      } else {
        console.log("Not mobile (width:", window.innerWidth, ")");
      }

      // Special handling for specific action types
      switch (actionType) {
        case "cv-tailor":
          // Trigger CV tailoring container after a delay
          setTimeout(() => {
            if (window.tailorCV && window.currentTailoringJob) {
              window.tailorCV(window.currentTailoringJob.id);
            } else {
              // Create a generic job object for tailoring
              window.tailorCV({
                id: "risk-role",
                title: "Risk Management Role",
                company: "Target PE Firm",
              });
            }
          }, 1000);
          break;

        case "cv-analyze":
          // Trigger CV analysis
          setTimeout(() => {
            if (window.currentCVContent) {
              window.sennaConversational.addSennaMessage(
                "I'm analyzing your CV now. This comprehensive analysis will identify strengths, gaps, and specific improvements for PE roles..."
              );
            }
          }, 1000);
          break;

        case "compare":
          // Load saved jobs for comparison
          const savedJobs = JSON.parse(
            localStorage.getItem("sffc_saved_jobs") || "[]"
          );
          if (savedJobs.length >= 2) {
            setTimeout(() => {
              window.sennaConversational.addSennaMessage(
                `I'll compare these ${savedJobs.length} opportunities for you...`
              );
            }, 1000);
          }
          break;
      }

      // Track action
      this.trackAction(cardId, actionType);
    }

    /**
     * Handle missing CV scenario
     */
    handleMissingCV() {
      if (window.sennaConversational) {
        const message =
          "I need to see your CV first. Can you upload it so I can help you?";

        // Use handleUserInput for proper message handling
        if (window.sennaConversational.handleUserInput) {
          window.sennaConversational.handleUserInput(message);
        } else {
          window.sennaConversational.addUserMessage(message);
        }

        // Show CV upload interface
        setTimeout(() => {
          if (window.showCVUploadInterface) {
            window.showCVUploadInterface();
          }
        }, 1000);
      }
    }

    /**
     * Handle no jobs viewed scenario
     */
    handleNoJobs() {
      if (window.sennaConversational) {
        const message = "Let me show you some job opportunities first";

        // Use handleUserInput for proper message handling
        if (window.sennaConversational.handleUserInput) {
          window.sennaConversational.handleUserInput(message);
        } else {
          window.sennaConversational.addUserMessage(message);
        }

        // Trigger job search
        setTimeout(() => {
          if (window.searchAndDisplayJobs) {
            window.searchAndDisplayJobs();
          }
        }, 1000);
      }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
      const container = $(".action-cards-container");
      if (container.length) {
        container.addClass("loading");
        container.prepend(
          '<div class="cards-loading-overlay"><div class="spinner"></div></div>'
        );
      }
    }

    /**
     * Hide loading state
     */
    hideLoadingState() {
      const container = $(".action-cards-container");
      if (container.length) {
        container.removeClass("loading");
        container.find(".cards-loading-overlay").remove();
      }
    }

    /**
     * Show notification
     */
    showNotification(message, type = "info") {
      const notification = $(`
                <div class="action-notification ${type}">
                    ${message}
                </div>
            `);

      $("body").append(notification);

      setTimeout(() => {
        notification.fadeOut(() => notification.remove());
      }, 3000);
    }

    /**
     * Track action usage
     */
    trackAction(cardId, actionType) {
      const usage = JSON.parse(
        localStorage.getItem("sffc_action_usage") || "{}"
      );

      if (!usage[cardId]) {
        usage[cardId] = {
          count: 0,
          lastUsed: null,
          actionType: actionType,
        };
      }

      usage[cardId].count++;
      usage[cardId].lastUsed = Date.now();

      localStorage.setItem("sffc_action_usage", JSON.stringify(usage));
    }

    /**
     * Escape HTML for data attributes
     */
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
  }

  // Initialize the system
  window.ActionCardsSystem = new ActionCardsSystem();

  // Add CSS for the new components
  const styles = `
        <style>
            /* Filter Tabs */
            .action-filter-tabs {
                display: flex;
                gap: 8px;
                padding: 12px 16px;
                background: rgba(245, 242, 232, 0.95);
                border-bottom: 1px solid #E0DDD3;
                overflow-x: auto;
                scrollbar-width: none;
            }
            
            .action-filter-tabs::-webkit-scrollbar {
                display: none;
            }
            
            .filter-tab {
                display: flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                background: transparent;
                border: 1px solid transparent;
                border-radius: 20px;
                color: #6B8E8F;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                white-space: nowrap;
            }
            
            .filter-tab:hover {
                background: rgba(255, 255, 255, 0.5);
                border-color: #E0DDD3;
            }
            
            .filter-tab.active {
                background: #0d353e;
                color: white;
                border-color: #0d353e;
            }
            
            .tab-icon {
                font-size: 16px;
            }
            
            .tab-count {
                display: inline-block;
                min-width: 18px;
                padding: 2px 6px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 10px;
                font-size: 11px;
                margin-left: 4px;
            }
            
            /* Action Cards Container */
            .action-cards-container {
                padding: 20px 16px;
                position: relative;
                min-height: 200px;
            }
            
            .action-cards-container.loading {
                opacity: 0.6;
            }
            
            /* Loading Overlay */
            .cards-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 100;
            }
            
            /* Action Trigger Button */
            .action-trigger-btn {
                width: 100%;
                padding: 16px;
                background: #F5F2E8;
                border: 2px solid #F5F2E8;
                border-radius: 12px;
                color: #0d353e;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }
            
            .action-trigger-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 30px rgba(245,242,232,0.3);
                background: white;
            }
            
            .action-trigger-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }
            
            .action-trigger-btn .spinner {
                display: inline-block;
                width: 14px;
                height: 14px;
                border: 2px solid #0d353e;
                border-top-color: transparent;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
            
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
            
            /* Metadata Tags */
            .meta-tag {
                display: inline-block;
                padding: 4px 10px;
                background: rgba(255,255,255,0.15);
                border-radius: 12px;
                color: rgba(255,255,255,0.9);
                font-size: 12px;
                font-weight: 500;
            }
            
            .meta-tag.time-tag {
                background: rgba(255, 193, 7, 0.2);
                color: #FFC107;
            }
            
            /* Notifications */
            .action-notification {
                position: fixed;
                bottom: 20px;
                right: 20px;
                padding: 16px 24px;
                background: #0d353e;
                color: white;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 100000;
                animation: slideIn 0.3s ease;
            }
            
            .action-notification.error {
                background: #dc3545;
            }
            
            .action-notification.success {
                background: #28a745;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            /* Mobile Responsiveness */
            @media (max-width: 768px) {
                .action-filter-tabs {
                    padding: 8px 12px;
                }
                
                .filter-tab {
                    padding: 6px 10px;
                    font-size: 12px;
                }
                
                .tab-icon {
                    font-size: 14px;
                }
                
                .action-cards-container {
                    padding: 12px 8px;
                }
                
                .question-card.action-card {
                    min-height: auto;
                    max-height: none;
                    padding: 40px 16px 40px;
                }
                
                .action-trigger-btn {
                    padding: 14px;
                    font-size: 14px;
                }
                
                .trending-badge {
                    padding: 6px 10px;
                    font-size: 10px;
                }
                
                .question-category {
                    font-size: 11px;
                }
                
                .question-title {
                    font-size: 18px;
                }
                
                .question-preview {
                    font-size: 13px;
                }
            }
        </style>
    `;

  $("head").append(styles);
})(jQuery);
