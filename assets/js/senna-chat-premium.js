/**
 * MENA Careers AI Chat - Premium Vogue-Style Interface
 * Full-screen layout with magazine-quality aesthetics
 */

(function ($) {
  "use strict";

  class SennaPremiumChat {
    constructor() {
      this.container = null;
      this.shortlist = [];
      this.activeJob = null;
      this.messageHistory = [];
      this.userProfile = null;
      this.isTyping = false;

      this.init();
    }

    init() {
      this.loadUserProfile();
      this.loadShortlist();
      this.bindEvents();
      this.setupFloatingTrigger();
    }

    loadUserProfile() {
      // Load from localStorage or set defaults
      const savedProfile = localStorage.getItem("sffc_user_profile");
      if (savedProfile) {
        this.userProfile = JSON.parse(savedProfile);
      } else {
        this.userProfile = {
          name: "Strategic Professional",
          experience_years: 5,
          current_role: "Senior Analyst",
          target_roles: ["VP Finance", "Director of Strategy"],
          target_salary: 200,
          skills: ["Financial Modeling", "Strategic Planning", "M&A"],
          industries: ["Private Equity", "Investment Banking"],
          preferences: {
            work_style: "hybrid",
            company_size: "enterprise",
            culture: "performance-driven",
          },
        };
      }
    }

    loadShortlist() {
      const saved = localStorage.getItem("sffc_shortlist");
      if (saved) {
        this.shortlist = JSON.parse(saved);
      }
    }

    setupFloatingTrigger() {
      // Create premium trigger button
      if ($(".sffc-senna-trigger-premium").length === 0) {
        const trigger = $(`
                    <button class="sffc-senna-trigger-premium">
                        <img src="${
                          sffc_frontend?.plugin_url || ""
                        }assets/images/senna.jpeg" 
                             alt="MENA Careers AI" 
                             onerror="this.style.display='none'; this.parentElement.innerHTML='S';">
                    </button>
                `);

        $("body").append(trigger);

        trigger.on("click", () => this.openChat());
      }
    }

    openChat() {
      if (this.container && this.container.hasClass("active")) {
        return;
      }

      this.createPremiumInterface();
      this.container.addClass("active");
      this.loadShortlistedJobs();
      this.showWelcomeMessage();

      // Focus input
      setTimeout(() => {
        this.container.find(".sffc-chat-input").focus();
      }, 500);
    }

    createPremiumInterface() {
      if (this.container) {
        return;
      }

      const html = `
                <div class="sffc-senna-premium-container">
                    <div class="sffc-senna-layout">
                        <!-- Left Sidebar - Job Cards -->
                        <div class="sffc-senna-sidebar">
                            <div class="sffc-sidebar-header">
                                <h2 class="sffc-sidebar-title">Strategic Opportunities</h2>
                                <p class="sffc-sidebar-subtitle">Your Curated Selection</p>
                            </div>
                            <div class="sffc-job-cards-container">
                                <!-- Job cards will be inserted here -->
                            </div>
                        </div>
                        
                        <!-- Main Chat Area -->
                        <div class="sffc-senna-main">
                            <!-- Chat Header -->
                            <div class="sffc-chat-header">
                                <div class="sffc-header-left">
                                    <div class="sffc-senna-avatar">
                                        <img src="${
                                          sffc_frontend?.plugin_url || ""
                                        }assets/images/senna.jpeg" 
                                             alt="MENA Careers" 
                                             onerror="this.style.display='none'; this.parentElement.innerHTML='<span>S</span>';">
                                    </div>
                                    <div class="sffc-header-info">
                                        <h1 class="sffc-chat-title">MENA Careers</h1>
                                        <p class="sffc-chat-subtitle">Executive Career Strategist</p>
                                    </div>
                                </div>
                                <button class="sffc-close-chat">×</button>
                            </div>
                            
                            <!-- Chat Messages -->
                            <div class="sffc-chat-messages">
                                <!-- Messages will be inserted here -->
                            </div>
                            
                            <!-- Floating Chat Input -->
                            <div class="sffc-chat-input-container">
                                <div class="sffc-input-wrapper">
                                    <textarea class="sffc-chat-input" 
                                              placeholder="Ask about career strategy, role analysis, or market insights..."
                                              rows="1"></textarea>
                                    <button class="sffc-send-button">→</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $("body").append(html);
      this.container = $(".sffc-senna-premium-container");

      // Apply premium CSS if not already loaded
      if ($("#sffc-senna-premium-styles").length === 0) {
        $("head").append(`
                    <link id="sffc-senna-premium-styles" 
                          rel="stylesheet" 
                          href="${
                            sffc_frontend?.plugin_url || ""
                          }assets/css/senna-chat-premium.css">
                `);
      }

      this.bindInterfaceEvents();
    }

    bindInterfaceEvents() {
      const container = this.container;

      // Close button
      container.find(".sffc-close-chat").on("click", () => {
        container.removeClass("active");
        setTimeout(() => container.remove(), 500);
        this.container = null;
      });

      // Send message
      container.find(".sffc-send-button").on("click", () => {
        this.sendMessage();
      });

      // Enter key
      container.find(".sffc-chat-input").on("keypress", (e) => {
        if (e.which === 13 && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      // Auto-resize textarea
      container.find(".sffc-chat-input").on("input", function () {
        this.style.height = "auto";
        this.style.height = Math.min(this.scrollHeight, 120) + "px";
      });
    }

    loadShortlistedJobs() {
      const container = this.container.find(".sffc-job-cards-container");
      container.empty();

      if (this.shortlist.length === 0) {
        container.html(`
                    <div class="sffc-empty-state">
                        <p>Start learning to build your PE knowledge</p>
                    </div>
                `);
        return;
      }

      this.shortlist.forEach((job, index) => {
        const card = this.createJobCard(job, index);
        container.append(card);
      });

      // Bind job card events
      container.find(".sffc-job-card").on(
        "click",
        function () {
          const jobId = $(this).data("job-id");
          const job = this.shortlist.find((j) => j.id === jobId);
          if (job) {
            this.analyzeJob(job);
            $(this).addClass("active").siblings().removeClass("active");
          }
        }.bind(this)
      );
    }

    createJobCard(job, index) {
      return `
                <div class="sffc-job-card" data-job-id="${job.id || index}">
                    <div class="sffc-job-company">${job.company}</div>
                    <h3 class="sffc-job-title">${job.title}</h3>
                    <div class="sffc-job-meta">
                        <span>${job.location || "Remote"}</span>
                        <span>${job.type || "Full-time"}</span>
                    </div>
                    <div class="sffc-job-match">
                        <span class="sffc-match-label">Match Score</span>
                        <span class="sffc-match-score">${
                          job.match_score || 85
                        }%</span>
                    </div>
                </div>
            `;
    }

    showWelcomeMessage() {
      const welcomeHtml = `
                <div class="sffc-message-analysis">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">Welcome to Your Strategic Career Command Center</h2>
                        <p class="sffc-analysis-subtitle">Let's elevate your career trajectory together</p>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">◆</span>
                            Your Portfolio Analysis
                        </h3>
                        <div class="sffc-analysis-content">
                            <p>You have <span class="sffc-highlight">${
                              this.shortlist.length
                            } strategic opportunities</span> in your pipeline.</p>
                            ${
                              this.shortlist.length > 0
                                ? this.getPortfolioInsights()
                                : this.getEmptyStateMessage()
                            }
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Market Position</h4>
                            <p>Based on your profile, you're positioned in the top 15% of candidates for senior finance roles.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Compensation Range</h4>
                            <p>Your target roles command $${
                              this.userProfile.target_salary
                            }k-${
        this.userProfile.target_salary * 1.3
      }k in current market conditions.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Strategic Timing</h4>
                            <p>Q1 is optimal for senior moves. Decision makers are planning annual strategies now.</p>
                        </div>
                    </div>
                </div>
            `;

      this.container.find(".sffc-chat-messages").html(welcomeHtml);
    }

    getPortfolioInsights() {
      const avgScore =
        this.shortlist.reduce((sum, job) => sum + (job.match_score || 85), 0) /
        this.shortlist.length;
      const topCompanies = this.shortlist
        .slice(0, 3)
        .map((j) => j.company)
        .join(", ");

      return `
                <ul class="sffc-analysis-list">
                    <li>Average match score: <strong>${Math.round(
                      avgScore
                    )}%</strong> - Exceptional alignment</li>
                    <li>Top targets: ${topCompanies}</li>
                    <li>Recommended action: Focus on top 3 for maximum impact</li>
                    <li>Timeline: Apply within 48 hours for optimal positioning</li>
                </ul>
            `;
    }

    getEmptyStateMessage() {
      return `
                <p>Let's build your opportunity pipeline. I can help you:</p>
                <ul class="sffc-analysis-list">
                    <li>Identify roles that match your strategic goals</li>
                    <li>Analyze compensation and growth potential</li>
                    <li>Craft compelling narratives for applications</li>
                    <li>Prepare for executive-level interviews</li>
                </ul>
            `;
    }

    analyzeJob(job) {
      this.activeJob = job;

      const analysisHtml = `
                <div class="sffc-message-analysis">
                    <div class="sffc-analysis-header">
                        <h2 class="sffc-analysis-title">${job.title}</h2>
                        <p class="sffc-analysis-subtitle">${job.company} • ${
        job.location || "Remote"
      }</p>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">★</span>
                            Strategic Fit Analysis
                        </h3>
                        <div class="sffc-analysis-content">
                            <p>This role aligns exceptionally well with your trajectory toward ${
                              this.userProfile.target_roles[0]
                            }.</p>
                            <ul class="sffc-analysis-list">
                                <li><strong>Match Score: ${
                                  job.match_score || 85
                                }%</strong> - Top tier alignment</li>
                                <li><strong>Growth Path:</strong> Clear progression to VP within 2-3 years</li>
                                <li><strong>Compensation:</strong> $${
                                  job.salary_min || 180
                                }k-${job.salary_max || 250}k plus equity</li>
                                <li><strong>Culture Fit:</strong> Performance-driven environment matching your preferences</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">◈</span>
                            Application Strategy
                        </h3>
                        <div class="sffc-analysis-content">
                            <p><strong>Positioning:</strong> Emphasize your ${
                              this.userProfile.skills[0]
                            } expertise and ${
        this.userProfile.experience_years
      } years driving strategic initiatives.</p>
                            <p><strong>Key Differentiators:</strong></p>
                            <ul class="sffc-analysis-list">
                                <li>Quantify your impact: ROI, cost savings, revenue growth</li>
                                <li>Highlight cross-functional leadership experience</li>
                                <li>Demonstrate industry-specific knowledge</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">▸</span>
                            Interview Preparation Focus
                        </h3>
                        <div class="sffc-analysis-content">
                            <ul class="sffc-analysis-list">
                                <li>Prepare 3 strategic initiative case studies</li>
                                <li>Review ${
                                  job.company
                                }'s recent financial performance</li>
                                <li>Develop perspective on industry challenges</li>
                                <li>Practice behavioral responses using STAR method</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Application Timeline</h4>
                            <p>Apply within 24 hours. ${
                              job.company
                            } typically moves fast on senior roles.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Negotiation Leverage</h4>
                            <p>Your profile commands top quartile compensation. Target $${
                              (job.salary_max || 250) * 0.9
                            }k base minimum.</p>
                        </div>
                    </div>
                </div>
            `;

      this.addMessage("senna", analysisHtml, "analysis");
    }

    sendMessage() {
      const input = this.container.find(".sffc-chat-input");
      const message = input.val().trim();

      if (!message) return;

      // Add user message
      this.addMessage("user", message);

      // Clear input
      input.val("").css("height", "auto");

      // Show typing indicator
      this.showTypingIndicator();

      // Process message
      setTimeout(() => {
        this.processUserMessage(message);
      }, 1500);
    }

    processUserMessage(message) {
      const messageLower = message.toLowerCase();

      // Hide typing indicator
      this.hideTypingIndicator();

      // Analyze intent and respond
      if (
        messageLower.includes("compare") ||
        messageLower.includes("comparison")
      ) {
        this.showComparison();
      } else if (
        messageLower.includes("salary") ||
        messageLower.includes("compensation")
      ) {
        this.showSalaryAnalysis();
      } else if (
        messageLower.includes("interview") ||
        messageLower.includes("prepare")
      ) {
        this.showInterviewPrep();
      } else if (
        messageLower.includes("strategy") ||
        messageLower.includes("express interest")
      ) {
        this.showApplicationStrategy();
      } else {
        this.showStrategicResponse(message);
      }
    }

    showComparison() {
      if (this.shortlist.length < 2) {
        this.addMessage(
          "senna",
          "You need at least 2 opportunities in your shortlist to compare. Would you like to explore more roles?"
        );
        return;
      }

      const compareHtml = `
                <div class="sffc-message-analysis">
                    <h2 class="sffc-analysis-title">Strategic Role Comparison</h2>
                    
                    <div class="sffc-compare-container">
                        ${this.shortlist
                          .slice(0, 3)
                          .map(
                            (job) => `
                            <div class="sffc-compare-role">
                                <h3>${job.title}</h3>
                                <p>${job.company}</p>
                                
                                <div class="sffc-compare-metric">
                                    <div class="sffc-compare-metric-label">Match Score</div>
                                    <div class="sffc-compare-metric-value">${
                                      job.match_score || 85
                                    }%</div>
                                </div>
                                
                                <div class="sffc-compare-metric">
                                    <div class="sffc-compare-metric-label">Compensation</div>
                                    <div class="sffc-compare-metric-value">$${
                                      job.salary_max || 200
                                    }k</div>
                                </div>
                                
                                <div class="sffc-compare-metric">
                                    <div class="sffc-compare-metric-label">Growth Path</div>
                                    <div class="sffc-compare-metric-value">VP in 2-3 years</div>
                                </div>
                                
                                <div class="sffc-compare-metric">
                                    <div class="sffc-compare-metric-label">Culture Fit</div>
                                    <div class="sffc-compare-metric-value">Excellent</div>
                                </div>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">Strategic Recommendation</h3>
                        <p>Based on your profile and career goals, I recommend prioritizing <strong>${
                          this.shortlist[0].company
                        }</strong> for immediate application. The role offers optimal alignment with your trajectory toward ${
        this.userProfile.target_roles[0]
      }.</p>
                    </div>
                </div>
            `;

      this.addMessage("senna", compareHtml, "analysis");
    }

    showSalaryAnalysis() {
      const salaryHtml = `
                <div class="sffc-message-analysis">
                    <h2 class="sffc-analysis-title">Compensation Strategy Analysis</h2>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">◈</span>
                            Market Intelligence
                        </h3>
                        <div class="sffc-analysis-content">
                            <p>Based on your ${
                              this.userProfile.experience_years
                            } years of experience and target roles:</p>
                            <ul class="sffc-analysis-list">
                                <li><strong>Market Range:</strong> $${
                                  this.userProfile.target_salary * 0.9
                                }k - $${
        this.userProfile.target_salary * 1.3
      }k base</li>
                                <li><strong>Your Target:</strong> $${
                                  this.userProfile.target_salary
                                }k (75th percentile)</li>
                                <li><strong>Total Comp:</strong> Add 30-50% for bonus and equity</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">
                            <span class="sffc-section-icon">★</span>
                            Negotiation Framework
                        </h3>
                        <div class="sffc-analysis-content">
                            <ul class="sffc-analysis-list">
                                <li><strong>Anchor High:</strong> Start at $${
                                  this.userProfile.target_salary * 1.2
                                }k</li>
                                <li><strong>Justify with Data:</strong> Reference market benchmarks and competing offers</li>
                                <li><strong>Package Focus:</strong> Negotiate signing bonus if base is capped</li>
                                <li><strong>Timing:</strong> Negotiate after verbal offer, before written</li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;

      this.addMessage("senna", salaryHtml, "analysis");
    }

    showInterviewPrep() {
      const prepHtml = `
                <div class="sffc-message-analysis">
                    <h2 class="sffc-analysis-title">Executive Interview Preparation</h2>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">Behavioral Excellence</h3>
                        <div class="sffc-analysis-content">
                            <p><strong>Key Questions to Master:</strong></p>
                            <ul class="sffc-analysis-list">
                                <li>"Walk me through a complex strategic initiative you led"</li>
                                <li>"How do you approach stakeholder management at C-level?"</li>
                                <li>"Describe a time you influenced without authority"</li>
                                <li>"How do you balance strategic vision with execution?"</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">Technical Preparation</h3>
                        <div class="sffc-analysis-content">
                            <ul class="sffc-analysis-list">
                                <li>Review latest industry trends and challenges</li>
                                <li>Prepare 90-day plan framework</li>
                                <li>Develop perspective on company's strategic priorities</li>
                                <li>Practice case study methodology</li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;

      this.addMessage("senna", prepHtml, "analysis");
    }

    showApplicationStrategy() {
      const strategyHtml = `
                <div class="sffc-message-analysis">
                    <h2 class="sffc-analysis-title">Application Strategy Blueprint</h2>
                    
                    <div class="sffc-analysis-section">
                        <h3 class="sffc-section-title">Immediate Actions</h3>
                        <div class="sffc-analysis-content">
                            <ol class="sffc-analysis-list">
                                <li><strong>Tonight:</strong> Customize resume for top 3 opportunities</li>
                                <li><strong>Tomorrow AM:</strong> Submit applications with tailored cover letters</li>
                                <li><strong>Tomorrow PM:</strong> LinkedIn outreach to hiring managers</li>
                                <li><strong>Day 3:</strong> Follow up with recruiters</li>
                            </ol>
                        </div>
                    </div>
                    
                    <div class="sffc-strategy-cards">
                        <div class="sffc-strategy-card">
                            <h4>Success Rate</h4>
                            <p>This approach yields 3x higher response rates than standard applications.</p>
                        </div>
                        <div class="sffc-strategy-card">
                            <h4>Timeline</h4>
                            <p>Expect initial responses within 5-7 business days with this strategy.</p>
                        </div>
                    </div>
                </div>
            `;

      this.addMessage("senna", strategyHtml, "analysis");
    }

    showStrategicResponse(message) {
      const response = `I understand you're asking about "${message}". Let me provide strategic insights tailored to your profile as a ${this.userProfile.current_role} with ${this.userProfile.experience_years} years of experience targeting ${this.userProfile.target_roles[0]} roles.`;

      this.addMessage("senna", response);

      // Add suggestions
      const suggestions = [
        "Analyze my shortlist strategically",
        "Compare top opportunities",
        "Salary negotiation tactics",
        "Interview preparation plan",
      ];

      this.addSuggestions(suggestions);
    }

    addMessage(sender, content, type = "text") {
      const messagesContainer = this.container.find(".sffc-chat-messages");
      const messageHtml =
        type === "analysis"
          ? content
          : `
                <div class="sffc-message sffc-message-${sender}">
                    ${
                      sender === "senna"
                        ? '<div class="senna-avatar">S</div>'
                        : ""
                    }
                    <div class="sffc-message-content">${content}</div>
                </div>
            `;

      messagesContainer.append(messageHtml);
      messagesContainer.scrollTop(messagesContainer[0].scrollHeight);

      // Save to history
      this.messageHistory.push({
        sender,
        content,
        type,
        timestamp: new Date(),
      });
    }

    addSuggestions(suggestions) {
      const html = `
                <div class="sffc-suggestions">
                    <span class="sffc-suggestions-label">Suggested Topics</span>
                    <div class="sffc-suggestion-chips">
                        ${suggestions
                          .map(
                            (s) =>
                              `<button class="sffc-suggestion-chip">${s}</button>`
                          )
                          .join("")}
                    </div>
                </div>
            `;

      this.container.find(".sffc-chat-messages").append(html);

      // Bind click events
      this.container.find(".sffc-suggestion-chip").on(
        "click",
        function () {
          const text = $(this).text();
          this.container.find(".sffc-chat-input").val(text);
          this.sendMessage();
        }.bind(this)
      );
    }

    showTypingIndicator() {
      if (this.isTyping) return;

      this.isTyping = true;
      const indicator = `
                <div class="sffc-typing-indicator">
                    <div class="senna-avatar">S</div>
                    <div class="sffc-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;

      this.container.find(".sffc-chat-messages").append(indicator);
      this.container
        .find(".sffc-chat-messages")
        .scrollTop(this.container.find(".sffc-chat-messages")[0].scrollHeight);
    }

    hideTypingIndicator() {
      this.isTyping = false;
      this.container.find(".sffc-typing-indicator").remove();
    }

    bindEvents() {
      // Listen for shortlist updates
      $(document).on("sffc:shortlist:updated", () => {
        this.loadShortlist();
        if (this.container) {
          this.loadShortlistedJobs();
        }
      });

      // Listen for analyze button clicks
      $(document).on("click", ".sffc-analyze-btn", () => {
        this.openChat();
      });
    }
  }

  // Initialize when ready
  $(document).ready(() => {
    // Only initialize if not already initialized
    if (!window.sennaPremiumChat) {
      window.sennaPremiumChat = new SennaPremiumChat();
    }
  });
})(jQuery);
