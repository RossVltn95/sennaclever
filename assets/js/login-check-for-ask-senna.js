/**
 * Login Check for Ask MENA Careers Buttons
 * Ensures ask-senna-btn clicks show join card for logged out users
 */
(function ($) {
  "use strict";

  // Login check handler for ask-senna buttons
  const LoginCheckHandler = {
    init() {
      this.interceptAskSennaClicks();
      this.debugLogging();
    },

    /**
     * Check if user is logged in based on available data
     */
    isUserLoggedIn() {
      const ajax = window.sffc_ajax || {};
      const frontend = window.sffc_frontend || {};
      const loginCheck = window.sffc_login_check || {};

      return !!(
        // Check our dedicated login check data first
        (
          loginCheck.user_logged_in ||
          loginCheck.is_logged_in === "1" ||
          loginCheck.is_logged_in === true ||
          parseInt(loginCheck.user_id || 0, 10) > 0 ||
          // Fallback to existing data sources
          ajax.user_logged_in ||
          ajax.is_logged_in === "1" ||
          ajax.is_logged_in === true ||
          frontend.is_logged_in === "1" ||
          frontend.is_logged_in === true ||
          parseInt(ajax.user_id || frontend.user_id || 0, 10) > 0
        )
      );
    },

    /**
     * Show the senna-join-card in chat for logged out users
     */
    showJoinCardInChat() {
      console.log("[LoginCheck] Showing join card for logged out user");

      // Method 1: Use existing SkillFarmAccess system
      if (
        window.SkillFarmAccess &&
        typeof window.SkillFarmAccess.showPrompt === "function"
      ) {
        console.log("[LoginCheck] Using SkillFarmAccess.showPrompt");
        window.SkillFarmAccess.showPrompt("ask-senna-btn-action", {
          force: true,
          source: "ask-senna-button",
        });
        return;
      }

      // Method 2: Use MENA Careers chat system directly
      if (
        window.SennaChat &&
        typeof window.SennaChat.showJoinPrompt === "function"
      ) {
        console.log("[LoginCheck] Using SennaChat.showJoinPrompt");
        window.SennaChat.showJoinPrompt();
        return;
      }

      // Method 3: Manually inject join card into chat
      this.injectJoinCardToChat();
    },

    /**
     * Manually inject the join card into the chat interface
     */
    injectJoinCardToChat() {
      console.log("[LoginCheck] Manually injecting join card to chat");

      const chatContainer = $(
        ".sffc-chat-messages, .senna-chat-messages, .chat-messages, #senna-chat-container .messages"
      );

      if (chatContainer.length === 0) {
        console.warn(
          "[LoginCheck] No chat container found, showing fallback modal"
        );
        this.showFallbackModal();
        return;
      }

      // Get plugin URL for assets
      const loginCheck = window.sffc_login_check || {};
      const pluginUrl =
        loginCheck.plugin_url ||
        window.sffc_frontend?.plugin_url ||
        window.sffc_ajax?.plugin_url ||
        "";

      // Get join/login URLs
      const joinUrl =
        loginCheck.join_url ||
        window.sffc_frontend?.join_url ||
        window.sffc_ajax?.join_url ||
        "#";
      const loginUrl =
        loginCheck.login_url ||
        window.sffc_frontend?.login_url ||
        window.sffc_ajax?.login_url ||
        "#";

      // Create current time
      const now = new Date();
      const timeStr = now.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });

      const joinCardHtml = `
                <div class="sffc-message senna-message senna-join-prompt">
                    <div class="sffc-message-header">
                        <div class="senna-avatar">
                            <img src="${pluginUrl}assets/images/senna.jpeg" alt="MENA Careers" onerror="this.style.display='none'">
                        </div>
                        <span class="sffc-message-senna-name">Private Equity Tutor</span>
                    </div>
                    <div class="sffc-message-content">
                        <div class="senna-join-card">
                            <span class="senna-join-eyebrow">Pro+ Access Required</span>
                            <p class="senna-join-title">Please log in to continue</p>
                            <p class="senna-join-body">This feature requires a senna account. Join to unlock AI-powered career guidance, CV optimization, and exclusive job opportunities.</p>
                            <div class="senna-join-actions">
                                <a class="senna-join-button" href="${joinUrl}" target="_blank" rel="noopener">Join senna</a>
                                <a class="senna-login-link" href="https://joinsenna.com/login-auth/">Already have an account? Log in</a>
                            </div>
                        </div>
                        <span class="sffc-message-time">${timeStr}</span>
                    </div>
                </div>
            `;

      // Add the join card to chat
      chatContainer.append(joinCardHtml);

      // Scroll to bottom
      setTimeout(() => {
        chatContainer.scrollTop(chatContainer[0].scrollHeight);
      }, 100);

      // Add pulsing animation
      setTimeout(() => {
        $(".senna-join-prompt").addClass("is-pulsing");
      }, 200);

      // Ensure styles are loaded
      this.ensureJoinCardStyles();
    },

    /**
     * Ensure join card styles are available
     */
    ensureJoinCardStyles() {
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
                    max-width: 400px;
                    margin: 0 auto;
                }
                .senna-join-eyebrow {
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    color: #2d6a4f;
                    margin: 0;
                }
                .senna-join-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: #1a472a;
                    margin: 0;
                    line-height: 1.3;
                }
                .senna-join-body {
                    font-size: 14px;
                    color: #2d6a4f;
                    margin: 0;
                    line-height: 1.4;
                }
                .senna-join-actions {
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    margin-top: 8px;
                }
                .senna-join-button {
                    background: linear-gradient(135deg, #2d6a4f, #1a472a);
                    color: white;
                    padding: 14px 20px;
                    border-radius: 12px;
                    text-decoration: none;
                    font-weight: 600;
                    font-size: 14px;
                    text-align: center;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 4px 12px rgba(26, 71, 42, 0.2);
                }
                .senna-join-button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 10px 24px rgba(26, 71, 42, 0.3);
                    text-decoration: none;
                    color: white;
                }
                .senna-login-link {
                    align-self: center;
                    font-size: 13px;
                    color: #2d6a4f;
                    text-decoration: underline;
                    font-weight: 500;
                }
                .senna-login-link:hover {
                    color: #1a472a;
                    text-decoration: underline;
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

    /**
     * Show fallback modal if chat container not found
     */
    showFallbackModal() {
      const loginCheck = window.sffc_login_check || {};
      const joinUrl =
        loginCheck.join_url ||
        window.sffc_frontend?.join_url ||
        window.sffc_ajax?.join_url ||
        "#";
      const loginUrl =
        loginCheck.login_url ||
        window.sffc_frontend?.login_url ||
        window.sffc_ajax?.login_url ||
        "#";

      const modalHtml = `
                <div id="senna-login-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                ">
                    <div style="
                        background: white;
                        padding: 30px;
                        border-radius: 16px;
                        max-width: 400px;
                        width: 90%;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    ">
                        <h3 style="margin: 0 0 15px 0; color: #1a472a;">Login Required</h3>
                        <p style="margin: 0 0 20px 0; color: #2d6a4f;">This feature requires a senna account. Join to unlock AI-powered career guidance.</p>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <a href="${joinUrl}" style="
                                background: linear-gradient(135deg, #2d6a4f, #1a472a);
                                color: white;
                                padding: 14px 20px;
                                border-radius: 12px;
                                text-decoration: none;
                                font-weight: 600;
                                text-align: center;
                            ">Join senna</a>
                            <a href="${loginUrl}" style="
                                color: #2d6a4f;
                                text-decoration: underline;
                                text-align: center;
                                font-size: 14px;
                            ">Already have an account? Log in</a>
                        </div>
                        <button onclick="document.getElementById('senna-login-modal').remove()" style="
                            position: absolute;
                            top: 10px;
                            right: 15px;
                            background: none;
                            border: none;
                            font-size: 20px;
                            cursor: pointer;
                            color: #666;
                        ">×</button>
                    </div>
                </div>
            `;

      $("body").append(modalHtml);
    },

    /**
     * Intercept ask-senna-btn clicks to check login status
     */
    interceptAskSennaClicks() {
      // Use high priority event delegation to catch clicks before other handlers
      $(document).on(
        "click.loginCheck",
        ".ask-senna-btn, .action-trigger-btn.ask-senna-btn",
        (e) => {
          console.log("[LoginCheck] Ask MENA Careers button clicked");

          // Check if user is logged in
          if (!this.isUserLoggedIn()) {
            console.log(
              "[LoginCheck] User not logged in, preventing action and showing join card"
            );

            // Prevent the default action
            e.preventDefault();
            e.stopImmediatePropagation();

            // Show join card in chat
            this.showJoinCardInChat();

            // Focus chat area if available
            setTimeout(() => {
              const chatInput = $(
                ".sffc-chat-input, .senna-chat-input, #user-message"
              );
              if (chatInput.length) {
                chatInput.focus();
              }
            }, 500);

            return false;
          }

          console.log("[LoginCheck] User is logged in, allowing normal action");
          // User is logged in, allow normal processing
        }
      );
    },

    /**
     * Debug logging to help with troubleshooting
     */
    debugLogging() {
      console.log("[LoginCheck] Initialized login check for ask-senna buttons");
      console.log("[LoginCheck] User logged in status:", this.isUserLoggedIn());
      console.log("[LoginCheck] Available data:", {
        sffc_login_check: window.sffc_login_check,
        sffc_ajax: window.sffc_ajax,
        sffc_frontend: window.sffc_frontend,
        SkillFarmAccess: !!window.SkillFarmAccess,
        SennaChat: !!window.SennaChat,
      });
    },
  };

  // Initialize when DOM is ready
  $(document).ready(() => {
    LoginCheckHandler.init();
  });

  // Also initialize if already loaded
  if (
    document.readyState === "complete" ||
    document.readyState === "interactive"
  ) {
    LoginCheckHandler.init();
  }

  // Make available globally for debugging
  window.LoginCheckHandler = LoginCheckHandler;
})(jQuery);
