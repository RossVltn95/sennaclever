/**
 * MENA Careers AI Chat Interface
 * Premium chat experience with contextual intelligence
 */

(function ($) {
  "use strict";

  // Verify jQuery is loaded
  if (typeof $ === "undefined") {
    console.error("SFFC Error: jQuery is not loaded!");
    return;
  }

  // Verify AJAX object
  console.log("SFFC Init - AJAX Config:", window.sffc_ajax);

  // State management
  const SennaChat = {
    isOpen: false,
    conversationId: null,
    messageHistory: [],
    userContext: {
      // Include career journey from localized data if available
      career_journey: window.sffc_ajax?.career_journey || {}
    },
    isTyping: false,
    quickPrompts: [],
    tutorContext: {
      active: false,
      systemPrompt: "",
      exerciseId: "",
      mode: "",
    },
  };

  /**
   * Shared access guard for gated interactions
   */
  const SkillFarmAccess = {
    promptReasons: new Set(),
    userMessageCount: 0,

    isLoggedIn() {
      if (window.SkillFarmAccess && window.SkillFarmAccess !== this) {
        // Another instance already controls access
        return window.SkillFarmAccess.isLoggedIn();
      }

      const ajax = window.sffc_ajax || {};
      const frontend = window.sffc_frontend || {};

      return !!(
        ajax.user_logged_in ||
        ajax.is_logged_in === "1" ||
        ajax.is_logged_in === true ||
        frontend.is_logged_in === "1" ||
        frontend.is_logged_in === true ||
        parseInt(ajax.user_id || frontend.user_id || 0, 10) > 0
      );
    },

    getJoinUrl() {
      const ajax = window.sffc_ajax || {};
      const authSettings = ajax.auth_settings || {};
      return (
        authSettings.registration_url ||
        ajax.registration_url ||
        window.sffc_registration_url ||
        "/join-senna/"
      );
    },

    getLoginUrl() {
      const ajax = window.sffc_ajax || {};
      const authSettings = ajax.auth_settings || {};
      return (
        authSettings.login_url ||
        ajax.login_url ||
        window.sffc_login_url ||
        "/login/"
      );
    },

    ensurePromptStyles() {
      if (document.getElementById("senna-join-styles")) {
        return;
      }

      const style = document.createElement("style");
      style.id = "senna-join-styles";
      style.textContent = `
                .senna-join-prompt .senna-join-card {
                    background: linear-gradient(135deg, #f6f1e7, #ffffff);
                    border: 1px solid rgba(26, 71, 42, 0.1);
                    border-radius: 16px;
                    padding: 18px 20px;
                    box-shadow: 0 10px 30px rgba(26, 71, 42, 0.1);
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                .senna-join-eyebrow {
                    font-size: 11px;
                    letter-spacing: 0.18em;
                    text-transform: uppercase;
                    color: rgba(26, 71, 42, 0.6);
                }
                .senna-join-title {
                    margin: 0;
                    font-size: 16px;
                    color: #1a472a;
                    font-weight: 700;
                }
                .senna-join-body {
                    margin: 0;
                    font-size: 14px;
                    color: #1f2d1f;
                    line-height: 1.5;
                }
                .senna-join-actions {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    margin-top: 6px;
                }
                .senna-join-button {
                    background: linear-gradient(135deg, #1a472a, #2d6a4f);
                    color: #fff;
                    padding: 10px 18px;
                    border-radius: 999px;
                    font-weight: 600;
                    text-decoration: none;
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                    box-shadow: 0 8px 20px rgba(26, 71, 42, 0.25);
                }
                .senna-join-button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 10px 24px rgba(26, 71, 42, 0.3);
                }
                .senna-login-link {
                    align-self: center;
                    font-size: 13px;
                    color: #2d6a4f;
                    text-decoration: underline;
                    font-weight: 500;
                }
                .senna-join-prompt.is-pulsing .senna-join-card {
                    animation: skillfarmJoinPulse 1.1s ease;
                }
                @keyframes skillfarmJoinPulse {
                    0% { box-shadow: 0 0 0 0 rgba(26, 71, 42, 0.2); }
                    70% { box-shadow: 0 0 0 10px rgba(26, 71, 42, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(26, 71, 42, 0); }
                }
            `;
      document.head.appendChild(style);
    },

    highlightPrompt($prompt) {
      if (!$prompt || !$prompt.length) return;
      $prompt.addClass("is-pulsing");
      setTimeout(() => $prompt.removeClass("is-pulsing"), 1200);
    },

    showPrompt(reason = "general", options = {}) {
      if (this.isLoggedIn()) {
        return false;
      }

      this.ensurePromptStyles();

      const $messages = $("#senna-messages");
      if (!$messages.length) {
        return false;
      }

      const existingPrompt = $messages.find(".senna-join-prompt").last();
      if (existingPrompt.length) {
        this.promptReasons.add(reason);

        if (options.force) {
          const $movedPrompt = existingPrompt.detach();
          $messages.append($movedPrompt);
          this.highlightPrompt($movedPrompt);
        } else {
          this.highlightPrompt(existingPrompt);
        }

        scrollToBottom();
        return true;
      }

      const joinUrl = this.getJoinUrl();
      const loginUrl = this.getLoginUrl();
      const promptHtml = `
                <div class="sffc-message sffc-message-senna senna-join-prompt" data-trigger="${reason}">
                    <div class="sffc-message-header">
                        <div class="senna-avatar">
                            <img src="${
                              window.sffc_frontend?.plugin_url ||
                              window.sffc_ajax?.plugin_url ||
                              ""
                            }assets/images/senna.jpeg" alt="MENA Careers">
                        </div>
                        <span class="sffc-message-senna-name">Private Equity Tutor</span>
                    </div>
                    <div class="sffc-message-content">
                        <div class="senna-join-card">
                            <span class="senna-join-eyebrow">Pro+ Access</span>
                            <p class="senna-join-title">Thank you for trying MENA Careers AI.</p>
                            <p class="senna-join-body">Ready to subscribe? Unlock full guided lessons, practice drills, and saved learning history.</p>
                            <div class="senna-join-actions">
                                <a class="senna-join-button" href="${joinUrl}" target="_blank" rel="noopener">Join senna</a>
                                <a class="senna-login-link" href="https://joinsenna.com/login-auth/">Log in instead</a>
                            </div>
                        </div>
                        <span class="sffc-message-time">${getCurrentTime()}</span>
                    </div>
                </div>
            `;

      $messages.append(promptHtml);
      const $newPrompt = $messages.find(".senna-join-prompt").last();
      this.highlightPrompt($newPrompt);
      this.promptReasons.add(reason);

      scrollToBottom();
      return true;
    },

    requireLogin(reason = "general") {
      if (this.isLoggedIn()) {
        return true;
      }

      this.showPrompt(reason, { force: true });
      return false;
    },

    recordUserMessage() {
      if (this.isLoggedIn()) {
        return;
      }

      this.userMessageCount += 1;

      if (this.userMessageCount === 3) {
        // ⏳ Small delay so the 3rd message renders first
        setTimeout(() => {
          this.showPrompt("message-threshold", { force: true });

          // 🔒 Disable chat input + send button
          $("#senna-input")
            .prop("disabled", true)
            .attr("placeholder", "Join senna to continue chatting...");
          $("#senna-send").prop("disabled", true);
        }, 300); // 300ms ensures the user’s message is already appended
      }
    },
  };

  window.SkillFarmAccess = SkillFarmAccess;

  // Initialize when DOM is ready
  $(document).ready(function () {
    if ($("#senna-chat-container").length === 0) return;

    console.log("SFFC: Initializing MENA Careers Chat...");

    // Initialize components
    initializeChatInterface();
    loadUserContext();
    loadQuickPrompts();
    bindEventHandlers();

    // Auto-open for testing (remove in production)
    if (window.location.hash === "#senna") {
      setTimeout(() => openChat(), 500);
    }
  });

  /**
   * Initialize chat interface
   */
  function initializeChatInterface() {
    // Generate conversation ID
    SennaChat.conversationId = generateUUID();

    // Set initial state
    const $interface = $("#senna-chat-interface");
    const mode = $("#senna-chat-container").data("mode");

    if (mode === "embedded") {
      $interface.show();
      SennaChat.isOpen = true;
    } else {
      $interface.hide();
    }

    // Initialize textarea auto-resize
    const $input = $("#senna-input");
    $input.on("input", function () {
      this.style.height = "auto";
      this.style.height = this.scrollHeight + "px";

      // Enable/disable send button
      $("#senna-send").prop("disabled", this.value.trim().length === 0);
    });
  }

  /**
   * Load user context
   */
  function loadUserContext() {
    // Get shortlist from localStorage
    const shortlist = JSON.parse(
      localStorage.getItem("sffc_shortlist") || "[]"
    );

    // Get other context via AJAX
    $.ajax({
      url: window.sffc_ajax?.url || "/wp-admin/admin-ajax.php",
      type: "POST",
      data: {
        action: "sffc_senna_get_context",
        nonce: window.sffc_ajax?.nonce || "",
      },
      success: function (response) {
        if (response.success) {
          SennaChat.userContext = {
            ...response.data.context,
            shortlist: shortlist,
          };
          console.log("SFFC: User context loaded", SennaChat.userContext);
        }
      },
    });
  }

  /**
   * Load quick prompts
   */
  function loadQuickPrompts() {
    const context = {
      shortlist: JSON.parse(localStorage.getItem("sffc_shortlist") || "[]"),
      page: window.location.pathname,
    };

    $.ajax({
      url: window.sffc_ajax?.url || "/wp-admin/admin-ajax.php",
      type: "POST",
      data: {
        action: "sffc_senna_quick_prompts",
        nonce: window.sffc_ajax?.nonce || "",
        context: JSON.stringify(context),
      },
      success: function (response) {
        if (response.success) {
          displayQuickPrompts(response.data.prompts);
        }
      },
    });
  }

  /**
   * Display quick prompts
   */
  function displayQuickPrompts(prompts) {
    const $container = $("#senna-prompts");
    $container.empty();

    if (!prompts || prompts.length === 0) return;

    const html = prompts
      .map(
        (prompt) => `
            <button class="sffc-quick-prompt" data-action="${prompt.action}">
                <span class="sffc-prompt-icon">${prompt.icon}</span>
                <span class="sffc-prompt-text">${prompt.text}</span>
            </button>
        `
      )
      .join("");

    $container.html(html);

    // Bind click handlers
    $(".sffc-quick-prompt").on("click", function () {
      const text = $(this).find(".sffc-prompt-text").text();
      const action = $(this).data("action");
      sendMessage(text, { quickAction: action });
    });
  }

  /**
   * Bind event handlers
   */
  function bindEventHandlers() {
    // Chat trigger button
    $("#senna-chat-trigger").on("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      openChat();
      return false;
    });

    // Close button with delegation for dynamic content
    $(document).on("click", ".sffc-senna-close", function (e) {
      e.preventDefault();
      e.stopPropagation();
      closeChat();
      return false;
    });

    // Send button with delegation
    $(document).on("click", "#senna-send:not(:disabled)", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const message = $("#senna-input").val().trim();
      if (message) {
        sendMessage(message);
      }
      return false;
    });

    // Enter key to send (Shift+Enter for new line)
    // Only bind if senna-conversational hasn't taken control
    if (!window.sennaInputControlled) {
      $("#senna-input").on("keydown.sennachat", function (e) {
        if (e.key === "Enter" && !e.shiftKey) {
          // Check if senna-conversational will handle this
          const message = $(this).val().trim();
          if (
            message &&
            window.sennaConversational &&
            typeof window.sennaConversational.isJobFilteringQuery === "function"
          ) {
            // If it's a job query, let senna-conversational handle it
            if (window.sennaConversational.isJobFilteringQuery(message)) {
              console.log("SennaChat: Deferring to job filtering system");
              return; // Let the keypress event bubble to senna-conversational
            }
          }

          // Otherwise handle it here
          e.preventDefault();
          if (message) {
            sendMessage(message);
          }
        }
      });
    }

    // Action buttons with delegation for dynamic content
    $(document).on(
      "click",
      ".sffc-action-btn, .sffc-action-button, .sffc-suggestion-chip, .sffc-quick-prompt",
      function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $this = $(this);

        // Handle different action types
        if ($this.hasClass("sffc-suggestion-chip")) {
          const text = $this.text();
          sendMessage(text);
        } else if ($this.hasClass("sffc-action-button")) {
          // Check for prompts array
          const prompts = $this.data("prompts");
          if (prompts && Array.isArray(prompts) && prompts.length > 0) {
            const randomPrompt =
              prompts[Math.floor(Math.random() * prompts.length)];
            sendMessage(randomPrompt);
          } else {
            const action = $this.data("action");
            if (action) handleAction(action);
          }
        } else if ($this.hasClass("sffc-quick-prompt")) {
          const text = $this.find(".sffc-prompt-text").text() || $this.text();
          const action = $this.data("action");
          if (action) {
            handleAction(action);
          } else {
            sendMessage(text);
          }
        } else {
          const action = $this.data("action");
          if (action) {
            handleAction(action);
          }
        }
        return false;
      }
    );

    // ESC key to close
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && SennaChat.isOpen) {
        closeChat();
      }
    });
  }

  /**
   * Open chat interface
   */
  function openChat() {
    const $container = $("#senna-chat-container");
    const $interface = $("#senna-chat-interface");
    if ($container.data("mode") === "overlay") {
      $interface.fadeIn(300);
      $container.addClass("open");
    }

    SennaChat.isOpen = true;

    // Focus input
    setTimeout(() => {
      $("#senna-input").focus();
    }, 300);

    // Show welcome message if first time
    if (SennaChat.messageHistory.length === 0) {
      setTimeout(() => {
        addWelcomeMessage();
      }, 500);
    }
  }

  /**
   * Close chat interface
   */
  function closeChat() {
    const $container = $("#senna-chat-container");
    const $interface = $("#senna-chat-interface");
    if ($container.data("mode") === "overlay") {
      $interface.fadeOut(300);
      $container.removeClass("open");
    }

    SennaChat.isOpen = false;
  }

  /**
   * Send message to MENA Careers
   */
  function clearTutorMode() {
    SennaChat.tutorContext = {
      active: false,
      systemPrompt: "",
      exerciseId: "",
      mode: "",
    };
  }

  function applyTutorContext(normalizedContext = {}) {
    if (!normalizedContext || typeof normalizedContext !== "object" || Array.isArray(normalizedContext)) {
      normalizedContext = {};
    }

    if (normalizedContext.clearTutorMode) {
      clearTutorMode();
      delete normalizedContext.clearTutorMode;
    }

    const explicitMode = normalizedContext.mode ? normalizedContext.mode.toString() : "";
    if (explicitMode && explicitMode !== "pe_tutor") {
      clearTutorMode();
    }

    const activatingTutor =
      explicitMode === "pe_tutor" ||
      normalizedContext.persistTutorMode ||
      (!!normalizedContext.exercise_id && normalizedContext.exercise_id !== "");

    if (activatingTutor) {
      SennaChat.tutorContext.active = true;
      SennaChat.tutorContext.mode = "pe_tutor";
    }

    if (normalizedContext.system_prompt && SennaChat.tutorContext.active) {
      SennaChat.tutorContext.systemPrompt = normalizedContext.system_prompt;
    }

    if (normalizedContext.exercise_id && SennaChat.tutorContext.active) {
      SennaChat.tutorContext.exerciseId = normalizedContext.exercise_id;
    }

    if (SennaChat.tutorContext.active) {
      normalizedContext.mode = normalizedContext.mode || SennaChat.tutorContext.mode || "pe_tutor";

      if (!normalizedContext.system_prompt && SennaChat.tutorContext.systemPrompt) {
        normalizedContext.system_prompt = SennaChat.tutorContext.systemPrompt;
      }

      if (!normalizedContext.exercise_id && SennaChat.tutorContext.exerciseId) {
        normalizedContext.exercise_id = SennaChat.tutorContext.exerciseId;
      }
    }

    if (normalizedContext.persistTutorMode) {
      delete normalizedContext.persistTutorMode;
    }

    return normalizedContext;
  }

  function sendMessage(message, additionalContext = {}) {
    if (SennaChat.isTyping) return;

    let normalizedContext = {};
    if (typeof additionalContext === "string" && additionalContext.trim().length) {
      normalizedContext.system_prompt = additionalContext;
    } else if (
      additionalContext &&
      typeof additionalContext === "object" &&
      !Array.isArray(additionalContext)
    ) {
      normalizedContext = { ...additionalContext };
    }

    normalizedContext = applyTutorContext(normalizedContext);

    // 🚨 Restrict logged-out users
    if (window.SkillFarmAccess && !window.SkillFarmAccess.isLoggedIn()) {
      if (window.SkillFarmAccess.userMessageCount >= 3) {
        console.warn("Guest usage limit reached, blocking message");

        // Show the join prompt again (force highlight)
        window.SkillFarmAccess.showPrompt("message-threshold", { force: true });
        return; // 🚫 stop here
      }
    }

    // Add user message to chat
    addMessage("user", message);

    // Clear input
    $("#senna-input").val("").css("height", "auto");
    $("#senna-send").prop("disabled", true);

    // Show typing indicator
    showTypingIndicator();

    // Prepare context
    const context = {
      ...SennaChat.userContext,
      ...normalizedContext,
      messageHistory: SennaChat.messageHistory.slice(-5), // Last 5 messages for context
    };

    const isGeneralQuery = !!normalizedContext.isGeneralQuery;

    // Send to backend
    $.ajax({
      url: window.sffc_ajax?.url || "/wp-admin/admin-ajax.php",
      type: "POST",
      dataType: "json",
      data: {
        action: "sffc_senna_chat",
        nonce: window.sffc_ajax?.nonce || "",
        message: message,
        context: JSON.stringify(context),
        conversation_id: SennaChat.conversationId,
        is_general_query: isGeneralQuery ? 1 : 0,
      },
      success: function (response) {
        hideTypingIndicator();

        if (response.success) {
          displayResponse(response.data.response);

          if (response.data.conversation_id) {
            SennaChat.conversationId = response.data.conversation_id;
          }
        } else {
          addMessage(
            "error",
            "Sorry, I encountered an error. Please try again."
          );
        }
      },
      error: function (xhr, status, error) {
        hideTypingIndicator();
        console.error("SFFC MENA Careers Chat Error:", {
          status: xhr.status,
          statusText: xhr.statusText,
          responseText: xhr.responseText,
          error: error,
        });
        addMessage(
          "error",
          "Connection error. Please check your internet and try again."
        );
      },
    });
  }

  /**
   * Display MENA Careers's response
   */
  function displayResponse(response) {
    // Handle different response types
    if (typeof response === "string") {
      addMessage("senna", response);
    } else if (response.type === "text") {
      addMessage("senna", response.content);
    } else if (response.type === "analysis") {
      addMessage("senna", response.content);
      if (response.cards) {
        displayCards(response.cards);
      }
    } else if (response.type === "mixed") {
      if (response.content) {
        addMessage("senna", response.content);
      }
      if (response.cards) {
        displayCards(response.cards);
      }
    }

    // Display actions
    if (response.actions && response.actions.length > 0) {
      displayActions(response.actions);
    }

    // Display suggestions
    if (response.suggestions && response.suggestions.length > 0) {
      displaySuggestions(response.suggestions);
    }
  }

  /**
   * Add message to chat
   */
  function addMessage(sender, content) {
    const $messages = $("#senna-messages");

    // Create message element
    let messageHtml = "";

    if (sender === "senna") {
      messageHtml = `
                <div class="sffc-message sffc-message-senna">
                    <div class="sffc-message-header">
                        <div class="senna-avatar">
                            <img src="${
                              window.sffc_frontend?.plugin_url ||
                              window.sffc_ajax?.plugin_url ||
                              ""
                            }assets/images/senna.jpeg" 
                                 alt="MENA Careers" 
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='S';">
                        </div>
                        <span class="sffc-message-senna-name">Private Equity Tutor</span>
                    </div>
                    <div class="sffc-message-content">
                        ${formatMessage(content)}
                        <span class="sffc-message-time">${getCurrentTime()}</span>
                    </div>
                </div>
            `;
    } else if (sender === "user") {
      messageHtml = `
                <div class="sffc-message sffc-message-user">
                    <div class="sffc-message-content">
                        ${formatMessage(content)}
                        <span class="sffc-message-time">${getCurrentTime()}</span>
                    </div>
                </div>
            `;
    } else {
      // Error or system message
      messageHtml = `
                <div class="sffc-message sffc-message-${sender}">
                    <div class="sffc-message-content">
                        ${formatMessage(content)}
                        <span class="sffc-message-time">${getCurrentTime()}</span>
                    </div>
                </div>
            `;
    }

    // Add to chat
    $messages.append(messageHtml);

    // Add to history
    SennaChat.messageHistory.push({
      sender: sender,
      content: content,
      timestamp: new Date(),
    });

    // Track logged-out usage to surface premium prompt
    if (sender === "user" && window.SkillFarmAccess) {
      window.SkillFarmAccess.recordUserMessage();
    }

    // Scroll to bottom
    scrollToBottom();
  }

  /**
   * Display analysis cards
   */
  function displayCards(cards) {
    const $messages = $("#senna-messages");

    cards.forEach((card) => {
      let cardHtml = "";

      switch (card.type) {
        case "summary":
          cardHtml = createSummaryCard(card);
          break;
        case "salary":
          cardHtml = createSalaryCard(card);
          break;
        case "path":
          cardHtml = createPathCard(card);
          break;
        case "preferences":
          cardHtml = createPreferencesCard(card);
          break;
        default:
          cardHtml = createGenericCard(card);
      }

      $messages.append(`
                <div class="sffc-message sffc-message-card">
                    ${cardHtml}
                </div>
            `);
    });

    scrollToBottom();
  }

  /**
   * Create summary card
   */
  function createSummaryCard(card) {
    const metrics = card.metrics || {};
    return `
            <div class="sffc-analysis-card sffc-card-summary">
                <h4 class="sffc-card-title">${card.title}</h4>
                <div class="sffc-card-metrics">
                    <div class="sffc-metric">
                        <span class="sffc-metric-value">${
                          metrics.total || 0
                        }</span>
                        <span class="sffc-metric-label">Total Opportunities</span>
                    </div>
                    <div class="sffc-metric">
                        <span class="sffc-metric-value">${
                          metrics.high_match || 0
                        }</span>
                        <span class="sffc-metric-label">High Matches</span>
                    </div>
                    <div class="sffc-metric">
                        <span class="sffc-metric-value">${
                          metrics.salary_range || "N/A"
                        }</span>
                        <span class="sffc-metric-label">Salary Range</span>
                    </div>
                </div>
            </div>
        `;
  }

  /**
   * Create salary card
   */
  function createSalaryCard(card) {
    const data = card.data || {};
    return `
            <div class="sffc-analysis-card sffc-card-salary">
                <h4 class="sffc-card-title">${card.title}</h4>
                <div class="sffc-salary-range">
                    <div class="sffc-range-bar">
                        <div class="sffc-range-fill" style="width: ${
                          data.percentile || 50
                        }%"></div>
                        <div class="sffc-range-marker" style="left: ${
                          data.percentile || 50
                        }%"></div>
                    </div>
                    <div class="sffc-range-labels">
                        <span>Market Range: ${data.market_range}</span>
                        <span>Your Target: ${data.your_target}</span>
                    </div>
                </div>
                ${
                  data.factors
                    ? `
                    <div class="sffc-salary-factors">
                        ${Object.entries(data.factors)
                          .map(
                            ([key, value]) => `
                            <div class="sffc-factor">
                                <span>${key}</span>
                                <span class="${
                                  value.startsWith("+")
                                    ? "positive"
                                    : "negative"
                                }">${value}</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                `
                    : ""
                }
            </div>
        `;
  }

  /**
   * Create career path card
   */
  function createPathCard(card) {
    const stages = card.stages || [];
    return `
            <div class="sffc-analysis-card sffc-card-path">
                <h4 class="sffc-card-title">${card.title}</h4>
                <div class="sffc-career-path">
                    ${stages
                      .map(
                        (stage, index) => `
                        <div class="sffc-path-stage ${
                          index === 0 ? "current" : ""
                        }">
                            <div class="sffc-stage-marker"></div>
                            <div class="sffc-stage-content">
                                <h5>${stage.role}</h5>
                                <span>${stage.timeline}</span>
                            </div>
                        </div>
                    `
                      )
                      .join("")}
                </div>
            </div>
        `;
  }

  /**
   * Create preferences card
   */
  function createPreferencesCard(card) {
    const options = card.options || {};
    return `
            <div class="sffc-analysis-card sffc-card-preferences">
                <h4 class="sffc-card-title">${card.title}</h4>
                <div class="sffc-preferences">
                    ${Object.entries(options)
                      .map(
                        ([category, items]) => `
                        <div class="sffc-preference-group">
                            <label>${category}</label>
                            <div class="sffc-preference-options">
                                ${items
                                  .map(
                                    (item) => `
                                    <button class="sffc-option-btn" data-category="${category}" data-value="${item}">
                                        ${item}
                                    </button>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                    `
                      )
                      .join("")}
                </div>
            </div>
        `;
  }

  /**
   * Create generic card
   */
  function createGenericCard(card) {
    return `
            <div class="sffc-analysis-card">
                <h4 class="sffc-card-title">${card.title || "Analysis"}</h4>
                <div class="sffc-card-content">
                    ${JSON.stringify(card.data || card, null, 2)}
                </div>
            </div>
        `;
  }

  /**
   * Display action buttons
   */
  function displayActions(actions) {
    const $messages = $("#senna-messages");

    const actionsHtml = `
            <div>
                ${actions
                  .map((action) => {
                    // Generate data attributes for prompts if they exist
                    const promptsAttr = action.prompts
                      ? `data-prompts='${JSON.stringify(action.prompts)}'`
                      : "";

                    return `
                        <button class="sffc-action-button" 
                                data-action="${action.action}"
                                ${promptsAttr}
                                title="${
                                  action.prompts
                                    ? action.prompts[0]
                                    : action.label
                                }">
                            ${action.label}
                        </button>
                    `;
                  })
                  .join("")}
            </div>
        `;

    $messages.append(actionsHtml);

    // Bind handlers
    $(".sffc-action-button").on("click", function () {
      const $btn = $(this);
      const action = $btn.data("action");
      const prompts = $btn.data("prompts");

      // If prompts exist, use a random one, otherwise use the action
      if (prompts && Array.isArray(prompts) && prompts.length > 0) {
        const randomPrompt =
          prompts[Math.floor(Math.random() * prompts.length)];
        sendMessage(randomPrompt);
      } else {
        handleAction(action);
      }
    });

    scrollToBottom();
  }

  function escapeAttribute(value = "") {
    return value
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function normalizeSuggestionItem(item) {
    if (!item) {
      return null;
    }

    if (typeof item === "string") {
      const trimmed = item.trim();
      if (!trimmed) {
        return null;
      }
      return { label: trimmed, prompt: trimmed };
    }

    if (typeof item === "object") {
      const label = (item.label || item.prompt || "").trim();
      const prompt = (item.prompt || item.label || "").trim();
      if (!label || !prompt) {
        return null;
      }
      return {
        label,
        prompt,
      };
    }

    return null;
  }

  function buildLearningSuggestions() {
    return [
      {
        label: "Explain further",
        prompt:
          "Explain this concept in more detail and walk me through each step slowly.",
      },
      {
        label: "Give me an example",
        prompt:
          "Give me a worked example with actual numbers so I can see how the maths plays out.",
      },
      {
        label: "Test my knowledge",
        prompt:
          "Test my knowledge with a short question so I can check I understand.",
      },
    ];
  }

  /**
   * Display suggestions
   */
  function displaySuggestions(suggestions) {
    const $messages = $("#senna-messages");
    const finalSuggestions = buildLearningSuggestions();

    if (!finalSuggestions.length) {
      return;
    }

    const suggestionsHtml = `
            <div class="sffc-suggestions">
                <span class="sffc-suggestions-label">Learning shortcuts:</span>
                <div class="sffc-suggestion-chips">
                    ${finalSuggestions
                      .map(
                        (suggestion) => `
                        <button class="sffc-suggestion-chip" data-prompt="${escapeAttribute(
                          suggestion.prompt
                        )}">
                            ${suggestion.label}
                        </button>
                    `
                      )
                      .join("")}
                </div>
                <div class="sffc-lesson-controls">
                    <span class="sffc-lesson-controls-label">Lesson controls:</span>
                    <div class="sffc-lesson-control-buttons">
                        <button class="sffc-lesson-control" data-prompt="start">Start</button>
                        <button class="sffc-lesson-control" data-prompt="continue">Continue</button>
                        <button class="sffc-lesson-control" data-prompt="next">Next</button>
                    </div>
                </div>
            </div>
        `;

    $messages.append(suggestionsHtml);

    $(".sffc-suggestion-chip").on("click", function () {
      const prompt = $(this).data("prompt") || $(this).text();
      sendMessage(prompt);
    });

    $(".sffc-lesson-control").on("click", function () {
      const prompt = $(this).data("prompt") || $(this).text();
      sendMessage(prompt);
    });

    scrollToBottom();
  }

  /**
   * Handle action buttons
   */
  function handleAction(action) {
    switch (action) {
      case "analyze-shortlist":
        sendMessage("Please analyze my shortlisted opportunities", {
          action: "analyze_shortlist",
        });
        break;
      case "salary-benchmark":
        sendMessage("Show me salary benchmarks for my profile", {
          action: "salary_benchmark",
        });
        break;
      case "career-path":
        sendMessage("What's my potential career progression?", {
          action: "career_path",
        });
        break;
      case "browse":
        window.location.href = "/opportunities/";
        break;
      case "preferences":
        sendMessage("Help me define my job preferences", {
          action: "preferences",
        });
        break;
      default:
        sendMessage(`Execute action: ${action}`, { action: action });
    }
  }

  /**
   * Add welcome message
   */
  function addWelcomeMessage() {
    const hour = new Date().getHours();
    let greeting = "Hello";

    if (hour < 12) greeting = "Good morning";
    else if (hour < 18) greeting = "Good afternoon";
    else greeting = "Good evening";

    const welcomeMessage = `${greeting}! I'm MENA Careers, your AI career strategist. I'm here to help you navigate your career journey with personalized insights and strategic guidance. What would you like to explore today?`;

    addMessage("senna", welcomeMessage);

    // Load initial prompts
    loadQuickPrompts();
  }

  /**
   * Show typing indicator
   */
  function showTypingIndicator() {
    SennaChat.isTyping = true;

    const $messages = $("#senna-messages");
    const typingHtml = `
            <div class="sffc-typing-indicator" id="typing-indicator">
                <div class="senna-avatar">S</div>
                <div class="sffc-typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;

    $messages.append(typingHtml);
    scrollToBottom();
  }

  /**
   * Hide typing indicator
   */
  function hideTypingIndicator() {
    SennaChat.isTyping = false;
    $("#typing-indicator").remove();
  }

  /**
   * Format message content with enhanced styling
   */
  function formatMessage(content) {
    // Convert line breaks
    content = content.replace(/\n/g, "<br>");

    // Support for subheadings using ###
    content = content.replace(
      /^###\s+(.+)$/gm,
      '<h3 class="sffc-subheading">$1</h3>'
    );
    content = content.replace(
      /^##\s+(.+)$/gm,
      '<h2 class="sffc-subheading">$1</h2>'
    );

    // Highlight important keywords and phrases (between backticks)
    content = content.replace(
      /`([^`]+)`/g,
      '<span class="sffc-highlight">$1</span>'
    );

    // Convert markdown-style formatting
    content = content.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
    content = content.replace(/\*(.*?)\*/g, "<em>$1</em>");

    // Support for bullet points
    content = content.replace(/^[\*\-]\s+(.+)$/gm, "<li>$1</li>");
    content = content.replace(
      /(<li>.*<\/li>)/s,
      '<ul class="sffc-bullet-list">$1</ul>'
    );

    // Highlight finance-specific keywords automatically
    const financeKeywords = [
      // Firms and Industry Types
      "Private Equity",
      "Investment Banking",
      "Private Credit",
      "Asset Management",
      "Venture Capital",
      "Growth Equity",
      "Leveraged Buyout",
      "LBO",
      "M&A",
      "Mergers & Acquisitions",
      "Capital Markets",
      "Equity Research",
      "Sales & Trading",
      "Wealth Management",
      "Family Office",
      "Sovereign Wealth Fund",
      "Pension Fund",
      "Infrastructure",
      "Real Estate",
      "Credit Fund",
      "Distressed Debt",
      "Mezzanine",
      "Fund of Funds",
      "Secondaries",
      "Co-investment",
      "Direct Lending",
      "Private Credit",

      // Titles and Levels
      "VP",
      "Vice President",
      "Associate",
      "Analyst",
      "Director",
      "Managing Director",
      "Principal",
      "Partner",
      "Managing Partner",
      "Senior Associate",
      "Senior Analyst",
      "Executive Director",
      "Senior Vice President",
      "SVP",
      "EVP",
      "MD",
      "C-Suite",
      "Portfolio Manager",
      "Investment Professional",
      "Deal Team",
      "Investment Committee",

      // Major Firms
      "Goldman Sachs",
      "Morgan Stanley",
      "JPMorgan",
      "Bank of America",
      "Citi",
      "Blackstone",
      "KKR",
      "Apollo",
      "Carlyle",
      "Warburg Pincus",
      "TPG",
      "Bain Capital",
      "Silver Lake",
      "Vista Equity",
      "Thoma Bravo",
      "EQT",
      "CVC",
      "Advent",
      "Permira",
      "Bridgewater",
      "Citadel",
      "Two Sigma",
      "DE Shaw",
      "Point72",
      "Millennium",
      "McKinsey",
      "Bain",
      "BCG",
      "Oliver Wyman",
      "LEK",
      "Roland Berger",

      // Compensation Terms
      "salary",
      "compensation",
      "bonus",
      "carry",
      "carried interest",
      "benefits",
      "base salary",
      "all-in comp",
      "total compensation",
      "signing bonus",
      "guaranteed bonus",
      "performance bonus",
      "retention bonus",
      "equity",
      "stock options",
      "RSUs",
      "co-invest",
      "profit sharing",
      "deferred compensation",
      "clawback",
      "vesting",
      "package",

      // Deal and Finance Terms
      "portfolio company",
      "target company",
      "due diligence",
      "deal flow",
      "sourcing",
      "execution",
      "closing",
      "exit",
      "IPO",
      "trade sale",
      "recap",
      "refinancing",
      "leverage",
      "EBITDA",
      "multiple",
      "valuation",
      "DCF",
      "LTM",
      "NTM",
      "IRR",
      "MOIC",
      "DPI",
      "TVPI",
      "J-curve",
      "waterfall",
      "hurdle rate",
      "preferred return",
      "LP",
      "GP",
      "Limited Partner",
      "General Partner",
      "fundraising",
      "commitment",

      // Skills and Qualifications
      "financial modeling",
      "LBO model",
      "merger model",
      "DCF model",
      "Excel",
      "PowerPoint",
      "pitch deck",
      "CIM",
      "teaser",
      "investment memo",
      "IC memo",
      "board deck",
      "CFA",
      "MBA",
      "top-tier",
      "bulge bracket",
      "elite boutique",
      "middle market",
      "sector coverage",
      "industry expertise",
      "deal experience",
      "transaction experience",

      // Work Culture Terms
      "work-life balance",
      "culture",
      "mentorship",
      "training program",
      "mobility",
      "promotion",
      "career progression",
      "exit opportunities",
      "placement",
      "alumni network",
      "hours",
      "travel",
      "client-facing",
      "internal",
      "lean team",
      "flat structure",
      "entrepreneurial",
      "collaborative",
      "fast-paced",
      "high-performance",
    ];

    financeKeywords.forEach((keyword) => {
      const regex = new RegExp(`\\b(${keyword})\\b`, "gi");
      content = content.replace(regex, '<span class="sffc-keyword">$1</span>');
    });

    // Convert URLs to links
    content = content.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank">$1</a>'
    );

    return content;
  }

  /**
   * Scroll to bottom of messages
   */
  function scrollToBottom() {
    const $messages = $("#senna-messages");
    if ($messages.length && $messages[0]) {
      $messages.animate(
        {
          scrollTop: $messages[0].scrollHeight,
        },
        300
      );
    }
  }

  /**
   * Get current time string
   */
  function getCurrentTime() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, "0");
    const minutes = now.getMinutes().toString().padStart(2, "0");
    return `${hours}:${minutes}`;
  }

  /**
   * Generate UUID
   */
  function generateUUID() {
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(
      /[xy]/g,
      function (c) {
        const r = (Math.random() * 16) | 0;
        const v = c === "x" ? r : (r & 0x3) | 0x8;
        return v.toString(16);
      }
    );
  }

  /**
   * Analyze shortlist - Premium feature
   */
  function analyzeShortlist(shortlist) {
    if (!shortlist || shortlist.length === 0) {
      sendMessage(
        "I notice you haven't shortlisted any opportunities yet. Let me help you find roles that match your profile."
      );
      return;
    }

    // Show analyzing state
    addMessage(
      "senna",
      `Analyzing your ${shortlist.length} shortlisted opportunities...`
    );
    showTypingIndicator();

    // Simulate analysis (in production, this would call the backend)
    setTimeout(() => {
      hideTypingIndicator();

      // Create comprehensive analysis
      const analysisHtml = createShortlistAnalysis(shortlist);
      displayAnalysisCard(analysisHtml);

      // Add follow-up suggestions
      setTimeout(() => {
        displaySuggestions([
          "Which role should I prioritize?",
          "Compare compensation packages",
          "Analyze cultural fit",
          "Create application timeline",
        ]);
      }, 500);
    }, 1500);
  }

  /**
   * Create premium shortlist analysis
   */
  function createShortlistAnalysis(shortlist) {
    const totalRoles = shortlist.length;
    const avgScore = Math.round(
      shortlist.reduce((sum, job) => sum + (job.match_score || 75), 0) /
        totalRoles
    );
    const highMatchCount = shortlist.filter(
      (job) => (job.match_score || 75) >= 80
    ).length;
    const topSalary = Math.max(
      ...shortlist.map((job) => job.salary_max || job.salary_min || 100000)
    );
    const minSalary = Math.min(
      ...shortlist.map((job) => job.salary_min || job.salary_max || 80000)
    );

    // Calculate strategic insights
    const competitionLevel =
      avgScore >= 85 ? "High" : avgScore >= 75 ? "Medium" : "Low";
    const applicationUrgency =
      highMatchCount > 2 ? "Apply within 48 hours" : "Apply this week";
    const salaryRange = `$${Math.round(minSalary / 1000)}k-$${Math.round(
      topSalary / 1000
    )}k`;

    return `
            <div class="sffc-premium-analysis">
                <h3 class="sffc-analysis-title">Strategic Career Analysis</h3>
                
                <div class="sffc-analysis-overview">
                    <div class="sffc-overview-stats">
                        <div class="sffc-stat">
                            <span class="sffc-stat-value">${totalRoles}</span>
                            <span class="sffc-stat-label">Opportunities</span>
                        </div>
                        <div class="sffc-stat">
                            <span class="sffc-stat-value">${avgScore}%</span>
                            <span class="sffc-stat-label">Avg Match</span>
                        </div>
                        <div class="sffc-stat">
                            <span class="sffc-stat-value">${highMatchCount}</span>
                            <span class="sffc-stat-label">Strong Matches</span>
                        </div>
                    </div>
                </div>
                
                <div class="sffc-analysis-insights">
                    <h4>Strategic Insights</h4>
                    <ul class="sffc-insights">
                        <li><strong>Portfolio Quality:</strong> ${
                          highMatchCount > totalRoles / 2
                            ? "Excellent alignment"
                            : "Good diversity"
                        } with your career goals</li>
                        <li><strong>Competition Level:</strong> ${competitionLevel} - ${applicationUrgency}</li>
                        <li><strong>Compensation Range:</strong> ${salaryRange} with ${
      avgScore >= 80 ? "strong" : "moderate"
    } negotiation potential</li>
                        <li><strong>Market Positioning:</strong> You're in the ${
                          avgScore >= 85
                            ? "top 10%"
                            : avgScore >= 75
                            ? "top 25%"
                            : "competitive range"
                        } of candidates</li>
                        <li><strong>Success Probability:</strong> ${
                          highMatchCount >= 2
                            ? "High (70-85%)"
                            : "Moderate (50-70%)"
                        } with proper preparation</li>
                    </ul>
                </div>
                
                <div class="sffc-shortlist-items">
                    <h4>Your Shortlisted Roles</h4>
                    ${shortlist
                      .map((job, index) => createShortlistCard(job, index + 1))
                      .join("")}
                </div>
                
                <div class="sffc-analysis-recommendations">
                    <h4>Action Plan</h4>
                    <div class="sffc-recommendations">
                        <div class="sffc-recommendation">
                            <span class="sffc-rec-icon">1</span>
                            <div class="sffc-rec-content">
                                <strong>Priority Application:</strong> ${
                                  shortlist[0]?.title || "Senior Role"
                                } at ${shortlist[0]?.company || "Leading Firm"}
                                <p>${
                                  shortlist[0]?.match_score >= 85
                                    ? "Exceptional fit -"
                                    : "Strong alignment -"
                                } apply within 24 hours for best results</p>
                            </div>
                        </div>
                        <div class="sffc-recommendation">
                            <span class="sffc-rec-icon">2</span>
                            <div class="sffc-rec-content">
                                <strong>Interview Preparation:</strong> Focus on ${
                                  avgScore >= 80
                                    ? "leadership & strategic thinking"
                                    : "technical expertise & problem-solving"
                                }
                                <p>Based on role requirements, emphasize your ${
                                  highMatchCount > 1
                                    ? "proven track record"
                                    : "growth potential"
                                }</p>
                            </div>
                        </div>
                        <div class="sffc-recommendation">
                            <span class="sffc-rec-icon">3</span>
                            <div class="sffc-rec-content">
                                <strong>Negotiation Strategy:</strong> Target ${Math.round(
                                  avgScore >= 85
                                    ? (topSalary * 0.95) / 1000
                                    : (topSalary * 0.85) / 1000
                                )}k base salary
                                <p>Your profile justifies ${
                                  avgScore >= 80 ? "top-tier" : "competitive"
                                } compensation in current market</p>
                            </div>
                        </div>
                        <div class="sffc-recommendation">
                            <span class="sffc-rec-icon">4</span>
                            <div class="sffc-rec-content">
                                <strong>Backup Strategy:</strong> Keep pipeline active with ${
                                  totalRoles < 5
                                    ? "2-3 more opportunities"
                                    : "current shortlist"
                                }
                                <p>${
                                  totalRoles < 3
                                    ? "Consider adding similar roles to strengthen position"
                                    : "Good portfolio diversification achieved"
                                }</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
  }

  /**
   * Create individual shortlist card with remove option
   */
  function createShortlistCard(job, rank) {
    const matchLevel =
      job.match_score >= 85
        ? "Exceptional"
        : job.match_score >= 75
        ? "Strong"
        : "Good";

    return `
            <div class="sffc-shortlist-card" data-job-id="${job.id}">
                <div class="sffc-card-rank">#${rank}</div>
                <div class="sffc-card-details">
                    <h5>${job.title}</h5>
                    <p>${job.company}</p>
                    <div class="sffc-card-meta">
                        <span class="sffc-match">${matchLevel} Match</span>
                        <span class="sffc-score">${
                          job.match_score || 75
                        }%</span>
                    </div>
                </div>
                <div class="sffc-card-actions">
                    <button class="sffc-analyze-job" data-job-id="${
                      job.id
                    }" title="Analyze this role">
                        <span>◉</span>
                    </button>
                    <button class="sffc-remove-job" data-job-id="${
                      job.id
                    }" title="Remove from shortlist">
                        <span>×</span>
                    </button>
                </div>
            </div>
        `;
  }

  /**
   * Display analysis card
   */
  function displayAnalysisCard(html) {
    const $messages = $("#senna-messages");

    const cardHtml = `
            <div class="sffc-message sffc-message-analysis">
                <div class="sffc-analysis-card sffc-card-premium">
                    ${html}
                </div>
            </div>
        `;

    $messages.append(cardHtml);

    // Bind remove handlers
    $(".sffc-remove-job").on("click", function () {
      const jobId = parseInt($(this).data("job-id"));
      removeFromShortlistInChat(jobId);
    });

    // Bind analyze handlers
    $(".sffc-analyze-job").on("click", function () {
      const jobId = parseInt($(this).data("job-id"));
      analyzeSpecificJob(jobId);
    });

    scrollToBottom();
  }

  /**
   * Remove from shortlist within chat
   */
  function removeFromShortlistInChat(jobId) {
    // Get current shortlist
    let shortlist = JSON.parse(localStorage.getItem("sffc_shortlist") || "[]");

    // Remove the job
    shortlist = shortlist.filter((item) => item.id !== jobId);

    // Save updated shortlist
    localStorage.setItem("sffc_shortlist", JSON.stringify(shortlist));

    // Update UI
    $(`.sffc-shortlist-card[data-job-id="${jobId}"]`).fadeOut(300, function () {
      $(this).remove();
    });

    // Update shortlist display in sidebar
    if (window.updateShortlistDisplay) {
      window.updateShortlistDisplay();
    }

    // Update opportunity card button if visible
    $(`.sffc-opportunity-card[data-job-id="${jobId}"] .sffc-btn-interested`)
      .removeClass("added")
      .text("Interested");

    // Show confirmation
    addMessage(
      "senna",
      `I've removed that opportunity from your shortlist. You now have ${shortlist.length} roles to focus on.`
    );

    // If shortlist is empty, offer to find more
    if (shortlist.length === 0) {
      setTimeout(() => {
        addMessage(
          "senna",
          "Your shortlist is now empty. Would you like me to help you find more opportunities that match your profile?"
        );
        displaySuggestions([
          "Find similar roles",
          "Broaden my search criteria",
          "Show trending opportunities",
          "Review my preferences",
        ]);
      }, 1000);
    }
  }

  /**
   * Analyze specific job
   */
  function analyzeSpecificJob(jobId) {
    const shortlist = JSON.parse(
      localStorage.getItem("sffc_shortlist") || "[]"
    );
    const job = shortlist.find((j) => j.id === jobId);

    if (job) {
      sendMessage(
        `Provide a detailed analysis of the ${job.title} role at ${job.company}, including fit assessment, preparation tips, and negotiation strategy.`
      );
    }
  }

  function processGeneralQuery(message) {
    const trimmed = (message || "").trim();
    if (!trimmed) {
      return;
    }

    sendMessage(trimmed, { isGeneralQuery: true, source: "action-card" });
  }

  // Public API
  window.SennaChat = {
    open: openChat,
    close: closeChat,
    send: sendMessage,
    processGeneralQuery: processGeneralQuery,
    analyzeShortlist: analyzeShortlist,
    endTutorSession: clearTutorMode,
  };
})(jQuery);
