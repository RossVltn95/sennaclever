/**
 * Application Audit V3 - Premium Wealth Management Experience
 *
 * JP Morgan Private Banking inspired UI with engaging sequence flow.
 * Dynamic questions from job requirements, premium report dashboard.
 *
 * @package SFFC
 * @since 11.0.0
 */

(function () {
  "use strict";

  // Premium SVG Icons - McKinsey Report Style
  const Icons = {
    check:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"></polyline></svg>',
    checkCircle:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
    warning:
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L1 21h22L12 2zm0 3.83L19.13 19H4.87L12 5.83zM11 16h2v2h-2v-2zm0-6h2v4h-2v-4z"></path></svg>',
    star: '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>',
    shield:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    target:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle></svg>',
    trending:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
    award:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
    briefcase:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
    arrowRight:
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
    arrowLeft:
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>',
    close:
      '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>',
    alertCircle:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
    zap: '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>',
    mapPin:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
    clock:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    fileText:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
    users:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    mail: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>',
    linkedin:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
    download:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>',
    building:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="6" x2="9" y2="6"></line><line x1="15" y1="6" x2="15" y2="6"></line><line x1="9" y1="10" x2="9" y2="10"></line><line x1="15" y1="10" x2="15" y2="10"></line><line x1="9" y1="14" x2="9" y2="14"></line><line x1="15" y1="14" x2="15" y2="14"></line><line x1="9" y1="18" x2="15" y2="18"></line></svg>',
    key: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>',
    copy: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>',
    externalLink:
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>',
    barChart:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>',
    globe:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>',
    plane:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path></svg>',
    passport:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="2" width="18" height="20" rx="2"></rect><circle cx="12" cy="10" r="3"></circle><path d="M7 22v-2a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"></path></svg>',
    dollarSign:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
    sun: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line></svg>',
    heart:
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>',
    home: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
  };

  // MENA Careers avatar URL
  const SENNA_AVATAR = "https://media.senna.com/2025/08/senna.webp?1755859811";

  // Typing effect function
  function typeMessage(element, text, callback) {
    if (!element || !text) {
      if (callback) callback();
      return;
    }

    element.textContent = "";
    element.style.visibility = "visible";

    let index = 0;
    const cursor = document.createElement("span");
    cursor.className = "audit-typing-cursor";
    cursor.textContent = "|";
    element.appendChild(cursor);

    function typeNext() {
      if (index < text.length) {
        const char = text.charAt(index);
        cursor.before(char);
        index++;

        let delay = 25;
        if (char === "." || char === "!" || char === "?") delay = 150;
        else if (char === ",") delay = 75;

        setTimeout(typeNext, delay);
      } else {
        setTimeout(() => {
          cursor.style.display = "none";
          if (callback) callback();
        }, 500);
      }
    }

    setTimeout(typeNext, 100);
  }

  class ApplicationAuditV3 {
    constructor(jobId, container) {
      this.jobId = jobId;
      this.container =
        typeof container === "string"
          ? document.querySelector(container)
          : container;
      this.config = null;
      this.responses = {};
      this.currentQuestionIndex = 0;
      this.questions = [];

      if (!this.container) {
        console.error("Audit container not found");
        return;
      }

      this.init();
    }

    async init() {
      this.showLoading();
      try {
        await this.fetchAuditConfig();
        this.questions = this.config.questions || [];
        if (this.questions.length > 0) {
          // Start with conversational sequence - this is the product
          this.renderConversationalSequence();
        } else {
          this.showError("No questions available for this role.");
        }
      } catch (error) {
        console.error("Audit init error:", error);
        this.showError("Unable to load assessment. Please try again.");
      }
    }

    async fetchAuditConfig() {
      const formData = new FormData();
      formData.append("action", "sffc_get_job_audit");
      formData.append("job_id", this.jobId);

      const response = await fetch(
        sffc_ajax?.ajaxurl || "/wp-admin/admin-ajax.php",
        {
          method: "POST",
          body: formData,
        }
      );

      const data = await response.json();
      if (data.success) {
        this.config = data.data;
      } else {
        throw new Error(data.data?.message || "Failed to load audit");
      }
    }

    showLoading() {
      this.container.innerHTML = `
                <div class="audit-loading">
                    <div class="audit-loading__icon">
                        <div class="audit-loading__ring"></div>
                        ${Icons.briefcase}
                    </div>
                    <div class="audit-loading__text">Analyzing Role Requirements</div>
                    <div class="audit-loading__subtext">Preparing your personalized assessment</div>
                </div>
            `;
    }

    showError(message) {
      this.container.innerHTML = `
                <div class="audit-loading audit-loading--error">
                    <div class="audit-loading__icon audit-loading__icon--error">
                        ${Icons.alertCircle}
                    </div>
                    <div class="audit-loading__text">${this.escapeHtml(
                      message
                    )}</div>
                    <button class="audit-btn audit-btn--primary" onclick="location.reload()">
                        Try Again
                    </button>
                </div>
            `;
    }

    escapeHtml(text) {
      if (!text) return "";
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }

    /**
     * Save audit responses to user profile
     * This stores the skill proficiency levels and experience data
     */
    saveToUserProfile(reportData) {
      // Extract skills proficiency from responses and category scores
      const skillsProficiency = {};
      const categoryScores = reportData.category_scores || {};

      // Map responses to skill proficiency levels
      Object.keys(this.responses).forEach((questionId) => {
        const response = this.responses[questionId];
        const question = this.questions.find((q) => q.id === questionId);

        if (question && question.skill_name) {
          // Convert score to proficiency level
          let level = "Basic";
          const score = parseInt(response) || 0;
          if (score >= 80) level = "Expert";
          else if (score >= 60) level = "Advanced";
          else if (score >= 40) level = "Intermediate";

          skillsProficiency[question.skill_name] = level;
        }
      });

      // Also add category-level scores
      Object.values(categoryScores).forEach((cat) => {
        if (cat.name && !skillsProficiency[cat.name]) {
          let level = "Basic";
          if (cat.percentage >= 80) level = "Expert";
          else if (cat.percentage >= 60) level = "Advanced";
          else if (cat.percentage >= 40) level = "Intermediate";

          skillsProficiency[cat.name] = level;
        }
      });

      // Extract experience from responses
      const experienceResponse = this.responses["experience_years"];
      let yearsExperience = "";
      if (experienceResponse) {
        // Map response to our format
        const expMap = {
          100: "10+",
          90: "7-10",
          80: "5-7",
          65: "3-5",
          50: "2-3",
          35: "1-2",
          15: "0-1",
          5: "0-1",
        };
        yearsExperience = expMap[experienceResponse] || "";
      }

      // Prepare profile data
      const profileData = new FormData();
      profileData.append("action", "sffc_save_audit_profile");
      profileData.append("nonce", sffc_ajax?.nonce || "");
      profileData.append("job_id", this.jobId);
      profileData.append(
        "skills_proficiency",
        JSON.stringify(skillsProficiency)
      );
      profileData.append("audit_responses", JSON.stringify(this.responses));

      if (yearsExperience) {
        profileData.append("years_experience", yearsExperience);
      }

      // Fire and forget - don't block the UI
      fetch(sffc_ajax?.ajaxurl || "/wp-admin/admin-ajax.php", {
        method: "POST",
        body: profileData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            console.log("Audit profile saved successfully");
          }
        })
        .catch((err) => {
          console.warn("Failed to save audit profile:", err);
        });
    }

    // Welcome screen - Location-focused intro, then straight into questions
    renderWelcome() {
      // Make fullscreen
      this.container.classList.add("sffc-audit-wrapper--fullscreen");
      document.body.style.overflow = "hidden";

      const job = this.config.job_data;
      const company = job.company || "this company";
      const location = job.location || "";
      const roleType = this.formatRoleName(job.role_category || "finance");

      // Extract city name for cleaner display
      const cityName = this.extractCityName(location);

      this.container.innerHTML = `
                <div class="audit-conversational">
                    <!-- Header -->
                    <div class="audit-conversational__header">
                        <div class="audit-conversational__logo">
                            ${Icons.mapPin}
                            <span>${cityName || "Career"} Opportunities</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="audit-conversational__body">
                        <div class="audit-conversational__container">
                            <!-- MENA Careers Avatar -->
                            <img src="${SENNA_AVATAR}" alt="MENA Careers" class="audit-senna-avatar" />

                            <!-- Welcome Message with Typing -->
                            <div class="audit-senna-message">
                                <div class="audit-senna-name">MENA Careers</div>
                                <div class="audit-senna-intro" id="senna-intro"></div>
                            </div>

                            <!-- Job Card -->
                            <div class="audit-job-card">
                                <div class="audit-job-card__icon">${
                                  Icons.briefcase
                                }</div>
                                <div class="audit-job-card__content">
                                    <div class="audit-job-card__title">${this.escapeHtml(
                                      job.job_title
                                    )}</div>
                                    <div class="audit-job-card__meta">
                                        ${this.escapeHtml(company)}
                                        ${
                                          location
                                            ? `<span class="audit-job-card__dot">•</span>${this.escapeHtml(
                                                location
                                              )}`
                                            : ""
                                        }
                                    </div>
                                </div>
                            </div>

                            <!-- Single CTA Button -->
                            <button class="audit-start-btn" id="start-btn" style="opacity: 0;">
                                Check my fit ${Icons.arrowRight}
                            </button>
                        </div>
                    </div>
                </div>
            `;

      // Typing effect for intro - location focused
      const introEl = document.getElementById("senna-intro");
      const startBtn = document.getElementById("start-btn");

      // Build location-aware intro message
      let introText;
      if (cityName) {
        introText = `Hi! I see you're exploring ${cityName} opportunities. Let me check if this ${this.escapeHtml(
          job.job_title
        )} role at ${this.escapeHtml(company)} is a good fit for you.`;
      } else {
        introText = `Hi! Let me help you figure out if this ${this.escapeHtml(
          job.job_title
        )} role at ${this.escapeHtml(company)} is right for you.`;
      }

      typeMessage(introEl, introText, () => {
        // Show button with animation
        setTimeout(() => {
          startBtn.style.transition = "opacity 0.5s ease";
          startBtn.style.opacity = "1";
        }, 300);
      });

      // Start button handler - go straight to questions
      startBtn.addEventListener("click", () => {
        startBtn.style.transform = "scale(0.98)";
        setTimeout(() => {
          this.renderConversationalSequence();
        }, 200);
      });
    }

    // Extract city name from location string (e.g., "Dubai, UAE" -> "Dubai")
    extractCityName(location) {
      if (!location) return "";
      // Common patterns: "City, Country", "City, State, Country", "City"
      const parts = location.split(",");
      return parts[0].trim();
    }

    formatRoleName(category) {
      if (!category) return "Professional";
      return category
        .split("_")
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(" ");
    }

    // Relocation-focused questions - conversational style
    renderRelocationQuestions() {
      const job = this.config.job_data;
      const location = job.location || "this destination";

      // Define relocation question flow
      this.relocationQuestions = [
        {
          id: "current_location",
          message: `Great choice! Moving for the right opportunity can be life-changing. First, where are you currently based?`,
          type: "cards",
          options: [
            { value: "uk", label: "United Kingdom", icon: Icons.home },
            { value: "usa", label: "United States", icon: Icons.home },
            { value: "eu", label: "Europe", icon: Icons.home },
            { value: "asia", label: "Asia Pacific", icon: Icons.home },
            { value: "private_equity", label: "private equity", icon: Icons.home },
            { value: "other", label: "Other", icon: Icons.globe },
          ],
        },
        {
          id: "relocation_timeline",
          message: `Got it! And how soon could you realistically make the move to ${this.escapeHtml(
            location
          )}?`,
          type: "cards",
          options: [
            {
              value: "immediate",
              label: "Immediately",
              desc: "Ready to go",
              icon: Icons.zap,
            },
            {
              value: "1-3months",
              label: "1-3 Months",
              desc: "Short notice",
              icon: Icons.clock,
            },
            {
              value: "3-6months",
              label: "3-6 Months",
              desc: "Need time to prepare",
              icon: Icons.clock,
            },
            {
              value: "exploring",
              label: "Just Exploring",
              desc: "Testing the waters",
              icon: Icons.globe,
            },
          ],
        },
        {
          id: "visa_status",
          message: `Important question - what's your work authorization situation for ${this.escapeHtml(
            location
          )}?`,
          type: "cards",
          options: [
            {
              value: "citizen",
              label: "Citizen / PR",
              desc: "Full rights",
              icon: Icons.checkCircle,
            },
            {
              value: "valid_visa",
              label: "Have Valid Visa",
              desc: "Already authorized",
              icon: Icons.passport,
            },
            {
              value: "need_sponsor",
              label: "Need Sponsorship",
              desc: "Employer support needed",
              icon: Icons.briefcase,
            },
            {
              value: "unsure",
              label: "Not Sure",
              desc: "Need to research",
              icon: Icons.alertCircle,
            },
          ],
        },
        {
          id: "motivation",
          message: `Last one - what's really driving your interest in this move? Be honest, it helps me give better advice.`,
          type: "cards",
          options: [
            {
              value: "career",
              label: "Career Growth",
              desc: "Better opportunities",
              icon: Icons.trending,
            },
            {
              value: "money",
              label: "Financial Upgrade",
              desc: "Higher comp / tax benefits",
              icon: Icons.dollarSign,
            },
            {
              value: "lifestyle",
              label: "Lifestyle Change",
              desc: "Quality of life",
              icon: Icons.sun,
            },
            {
              value: "adventure",
              label: "New Adventure",
              desc: "Ready for something new",
              icon: Icons.plane,
            },
            {
              value: "escape",
              label: "Fresh Start",
              desc: "Time for a change",
              icon: Icons.heart,
            },
          ],
        },
      ];

      this.currentRelocationIndex = 0;
      this.renderRelocationStep();
    }

    renderRelocationStep() {
      const question = this.relocationQuestions[this.currentRelocationIndex];
      const progress = Math.round(
        ((this.currentRelocationIndex + 1) / this.relocationQuestions.length) *
          100
      );
      const job = this.config.job_data;

      this.container.innerHTML = `
                <div class="audit-conversational">
                    <!-- Header with progress -->
                    <div class="audit-conversational__header">
                        <div class="audit-conversational__logo">
                            ${Icons.plane}
                            <span>Relocation Check</span>
                        </div>
                        <div class="audit-conversational__progress">
                            <div class="audit-conversational__progress-bar">
                                <div class="audit-conversational__progress-fill" style="width: ${progress}%"></div>
                            </div>
                            <span class="audit-conversational__progress-text">${
                              this.currentRelocationIndex + 1
                            } of ${this.relocationQuestions.length}</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="audit-conversational__body">
                        <div class="audit-conversational__container">
                            <!-- MENA Careers Avatar -->
                            <img src="${SENNA_AVATAR}" alt="MENA Careers" class="audit-senna-avatar" />

                            <!-- Message -->
                            <div class="audit-senna-message">
                                <div class="audit-senna-name">MENA Careers</div>
                                <div class="audit-senna-question" id="relocation-question"></div>
                            </div>

                            <!-- Visual Cards -->
                            <div class="audit-visual-cards audit-visual-cards--${
                              question.options.length > 4 ? "grid" : "flex"
                            }" id="relocation-cards" style="opacity: 0;">
                                ${question.options
                                  .map(
                                    (opt) => `
                                    <div class="audit-visual-card" data-value="${
                                      opt.value
                                    }">
                                        <div class="audit-visual-card__icon">${
                                          opt.icon
                                        }</div>
                                        <div class="audit-visual-card__title">${
                                          opt.label
                                        }</div>
                                        ${
                                          opt.desc
                                            ? `<div class="audit-visual-card__desc">${opt.desc}</div>`
                                            : ""
                                        }
                                    </div>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                    </div>

                    <!-- Footer with back button -->
                    ${
                      this.currentRelocationIndex > 0
                        ? `
                        <div class="audit-conversational__footer">
                            <button class="audit-back-btn" data-action="back">
                                ${Icons.arrowLeft} Back
                            </button>
                        </div>
                    `
                        : ""
                    }
                </div>
            `;

      // Type the question
      const questionEl = document.getElementById("relocation-question");
      const cardsEl = document.getElementById("relocation-cards");

      typeMessage(questionEl, question.message, () => {
        cardsEl.style.transition = "opacity 0.5s ease";
        cardsEl.style.opacity = "1";
      });

      // Card handlers
      this.container.querySelectorAll(".audit-visual-card").forEach((card) => {
        card.addEventListener("click", () => {
          const value = card.dataset.value;
          this.responses[question.id] = value;

          card.classList.add("audit-visual-card--selected");

          setTimeout(() => {
            if (
              this.currentRelocationIndex <
              this.relocationQuestions.length - 1
            ) {
              this.currentRelocationIndex++;
              this.renderRelocationStep();
            } else {
              // Done with relocation questions, proceed to role-specific questions
              // Use conversational style for those too
              this.renderConversationalSequence();
            }
          }, 400);
        });
      });

      // Back button handler
      const backBtn = this.container.querySelector('[data-action="back"]');
      if (backBtn) {
        backBtn.addEventListener("click", () => {
          this.currentRelocationIndex--;
          this.renderRelocationStep();
        });
      }
    }

    // Conversational style for role-specific questions
    renderConversationalSequence() {
      const job = this.config.job_data;
      const currentQ = this.questions[this.currentQuestionIndex];
      const totalQuestions = this.questions.length;
      const progress = Math.round(
        ((this.currentQuestionIndex + 1) / totalQuestions) * 100
      );

      // Category icons
      const categoryIcons = {
        Experience: Icons.briefcase,
        Skills: Icons.target,
        Qualifications: Icons.award,
        Fit: Icons.star,
      };
      const categoryIcon = categoryIcons[currentQ.category] || Icons.target;

      this.container.innerHTML = `
                <div class="audit-conversational">
                    <!-- Header with progress -->
                    <div class="audit-conversational__header">
                        <div class="audit-conversational__logo">
                            ${categoryIcon}
                            <span>${this.escapeHtml(currentQ.category)}</span>
                        </div>
                        <div class="audit-conversational__progress">
                            <div class="audit-conversational__progress-bar">
                                <div class="audit-conversational__progress-fill" style="width: ${progress}%"></div>
                            </div>
                            <span class="audit-conversational__progress-text">${
                              this.currentQuestionIndex + 1
                            } of ${totalQuestions}</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="audit-conversational__body">
                        <div class="audit-conversational__container">
                            <!-- MENA Careers Avatar -->
                            <img src="${SENNA_AVATAR}" alt="MENA Careers" class="audit-senna-avatar" />

                            <!-- Message -->
                            <div class="audit-senna-message">
                                <div class="audit-senna-name">MENA Careers</div>
                                <div class="audit-senna-question" id="role-question"></div>
                            </div>

                            <!-- Options as Visual Cards -->
                            <div class="audit-visual-cards audit-visual-cards--grid" id="role-options" style="opacity: 0;">
                                ${currentQ.options
                                  .map(
                                    (opt, idx) => `
                                    <div class="audit-visual-card audit-visual-card--option" data-value="${this.escapeHtml(
                                      opt.label
                                    )}" data-score="${opt.score}">
                                        <div class="audit-visual-card__title">${this.escapeHtml(
                                          opt.label
                                        )}</div>
                                    </div>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                    </div>

                    <!-- Footer with back button -->
                    <div class="audit-conversational__footer">
                        <button class="audit-back-btn" data-action="back">
                            ${Icons.arrowLeft} Back
                        </button>
                    </div>
                </div>
            `;

      // Type the question
      const questionEl = document.getElementById("role-question");
      const optionsEl = document.getElementById("role-options");

      const fullQuestion = currentQ.question;

      typeMessage(questionEl, fullQuestion, () => {
        optionsEl.style.transition = "opacity 0.5s ease";
        optionsEl.style.opacity = "1";
      });

      // Option handlers
      this.container
        .querySelectorAll(".audit-visual-card--option")
        .forEach((card) => {
          card.addEventListener("click", () => {
            const value = card.dataset.value;
            this.responses[currentQ.id] = value;

            card.classList.add("audit-visual-card--selected");

            setTimeout(() => {
              if (this.currentQuestionIndex < this.questions.length - 1) {
                this.currentQuestionIndex++;
                this.renderConversationalSequence();
              } else {
                // All questions done - generate report
                this.generateReport();
              }
            }, 400);
          });
        });

      // Back button handler
      const backBtn = this.container.querySelector('[data-action="back"]');
      if (backBtn) {
        backBtn.addEventListener("click", () => {
          if (this.currentQuestionIndex > 0) {
            this.currentQuestionIndex--;
            this.renderConversationalSequence();
          } else if (
            this.relocationQuestions &&
            this.relocationQuestions.length > 0
          ) {
            // Go back to last relocation question
            this.currentRelocationIndex = this.relocationQuestions.length - 1;
            this.renderRelocationStep();
          } else {
            // Go back to welcome
            this.renderWelcome();
          }
        });
      }
    }

    renderSequence() {
      const job = this.config.job_data;
      const currentQ = this.questions[this.currentQuestionIndex];
      const progress = Math.round(
        ((this.currentQuestionIndex + 1) / this.questions.length) * 100
      );
      const isLastQuestion =
        this.currentQuestionIndex === this.questions.length - 1;

      // Category icons
      const categoryIcons = {
        Experience: Icons.briefcase,
        Skills: Icons.target,
        Qualifications: Icons.award,
        Fit: Icons.star,
      };
      const categoryIcon = categoryIcons[currentQ.category] || Icons.target;

      this.container.innerHTML = `
                <div class="audit-sequence">
                    <!-- Minimal Header -->
                    <div class="audit-sequence__header">
                        <div class="audit-sequence__progress-info">
                            <span class="audit-sequence__step">${
                              this.currentQuestionIndex + 1
                            } of ${this.questions.length}</span>
                            <div class="audit-sequence__category">
                                ${categoryIcon}
                                <span>${this.escapeHtml(
                                  currentQ.category
                                )}</span>
                            </div>
                        </div>
                        <div class="audit-sequence__progress-bar">
                            <div class="audit-sequence__progress-fill" style="width: ${progress}%"></div>
                        </div>
                    </div>

                    <!-- Question Content -->
                    <div class="audit-sequence__body">
                        <div class="audit-question">
                            ${
                              currentQ.is_critical
                                ? `
                                <div class="audit-question__badge">
                                    ${Icons.star}
                                    <span>Key Requirement</span>
                                </div>
                            `
                                : ""
                            }

                            <h2 class="audit-question__text">${this.escapeHtml(
                              currentQ.question
                            )}</h2>

                            ${
                              currentQ.context
                                ? `
                                <p class="audit-question__context">${this.escapeHtml(
                                  currentQ.context
                                )}</p>
                            `
                                : ""
                            }

                            <div class="audit-options">
                                ${currentQ.options
                                  .map(
                                    (opt, idx) => `
                                    <button type="button"
                                            class="audit-option ${
                                              this.responses[currentQ.id] ===
                                              opt.label
                                                ? "audit-option--selected"
                                                : ""
                                            }"
                                            data-value="${this.escapeHtml(
                                              opt.label
                                            )}"
                                            data-score="${opt.score}"
                                            data-index="${idx}">
                                        <span class="audit-option__indicator">
                                            <span class="audit-option__check">${
                                              Icons.check
                                            }</span>
                                        </span>
                                        <span class="audit-option__content">
                                            <span class="audit-option__label">${this.escapeHtml(
                                              opt.label
                                            )}</span>
                                        </span>
                                    </button>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="audit-sequence__footer">
                        <div class="audit-sequence__nav">
                            ${
                              this.currentQuestionIndex > 0
                                ? `
                                <button class="audit-btn audit-btn--back" data-action="back">
                                    ${Icons.arrowLeft}
                                    <span>Back</span>
                                </button>
                            `
                                : "<div></div>"
                            }

                            <button class="audit-btn audit-btn--next ${
                              !this.responses[currentQ.id]
                                ? "audit-btn--disabled"
                                : ""
                            }"
                                    data-action="next"
                                    ${
                                      !this.responses[currentQ.id]
                                        ? "disabled"
                                        : ""
                                    }>
                                <span>${
                                  isLastQuestion ? "View Results" : "Continue"
                                }</span>
                                ${Icons.arrowRight}
                            </button>
                        </div>
                    </div>
                </div>
            `;

      this.attachSequenceListeners();
    }

    attachSequenceListeners() {
      const currentQ = this.questions[this.currentQuestionIndex];

      // Option selection with animation
      this.container.querySelectorAll(".audit-option").forEach((opt) => {
        opt.addEventListener("click", () => {
          // Update selection UI
          this.container.querySelectorAll(".audit-option").forEach((o) => {
            o.classList.remove("audit-option--selected");
          });
          opt.classList.add("audit-option--selected");

          // Store response
          this.responses[currentQ.id] = opt.dataset.value;

          // Enable and highlight next button
          const nextBtn = this.container.querySelector('[data-action="next"]');
          if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.classList.remove("audit-btn--disabled");
          }

          // Auto-advance after selection
          setTimeout(() => this.nextQuestion(), 400);
        });
      });

      // Navigation
      this.container
        .querySelector('[data-action="next"]')
        ?.addEventListener("click", () => this.nextQuestion());
      this.container
        .querySelector('[data-action="back"]')
        ?.addEventListener("click", () => this.prevQuestion());

      // Keyboard navigation
      document.addEventListener("keydown", this.handleKeyPress.bind(this));
    }

    handleKeyPress(e) {
      if (
        e.key === "Enter" &&
        this.responses[this.questions[this.currentQuestionIndex]?.id]
      ) {
        this.nextQuestion();
      }
      // Number keys for quick selection
      if (e.key >= "1" && e.key <= "9") {
        const options = this.container.querySelectorAll(".audit-option");
        const index = parseInt(e.key) - 1;
        if (options[index]) {
          options[index].click();
        }
      }
    }

    nextQuestion() {
      const currentQ = this.questions[this.currentQuestionIndex];
      if (!this.responses[currentQ.id]) return;

      // Remove keyboard listener
      document.removeEventListener("keydown", this.handleKeyPress);

      if (this.currentQuestionIndex < this.questions.length - 1) {
        this.currentQuestionIndex++;
        this.renderSequence();
      } else {
        this.generateReport();
      }
    }

    prevQuestion() {
      document.removeEventListener("keydown", this.handleKeyPress);
      if (this.currentQuestionIndex > 0) {
        this.currentQuestionIndex--;
        this.renderSequence();
      }
    }

    async generateReport() {
      this.container.innerHTML = `
                <div class="audit-loading">
                    <div class="audit-loading__icon">
                        <div class="audit-loading__ring"></div>
                        ${Icons.trending}
                    </div>
                    <div class="audit-loading__text">Calculating Your Score</div>
                    <div class="audit-loading__subtext">Analyzing responses against role requirements</div>
                    <div class="audit-loading__steps">
                        <div class="audit-loading__step audit-loading__step--active">Evaluating experience</div>
                        <div class="audit-loading__step">Assessing skills match</div>
                        <div class="audit-loading__step">Generating insights</div>
                    </div>
                </div>
            `;

      // Animate loading steps
      const steps = this.container.querySelectorAll(".audit-loading__step");
      let stepIndex = 0;
      const stepInterval = setInterval(() => {
        if (stepIndex < steps.length) {
          steps[stepIndex].classList.add("audit-loading__step--active");
          stepIndex++;
        }
      }, 400);

      try {
        const formData = new FormData();
        formData.append("action", "sffc_generate_audit_report");
        formData.append("job_id", this.jobId);
        formData.append("responses", JSON.stringify(this.responses));

        const response = await fetch(
          sffc_ajax?.ajaxurl || "/wp-admin/admin-ajax.php",
          {
            method: "POST",
            body: formData,
          }
        );

        const data = await response.json();
        clearInterval(stepInterval);

        if (data.success) {
          // Save responses to user audit profile
          this.saveToUserProfile(data.data);

          setTimeout(() => this.renderReport(data.data), 800);
        } else {
          throw new Error(data.data?.message || "Failed to generate report");
        }
      } catch (error) {
        clearInterval(stepInterval);
        console.error("Report generation error:", error);
        this.showError("Unable to generate report. Please try again.");
      }
    }

    renderReport(report) {
      const {
        health_score,
        health_grade,
        issues,
        category_scores,
        passed,
        recommendations,
        smart_apply,
        job_data,
        intelligence,
        comparison,
        charts,
        social_proof,
      } = report;

      // Store report for later use
      this.currentReport = report;

      // Extract job details
      const cityName = this.extractCityName(job_data.location || "");
      const roleCategory = job_data.role_category || "finance";
      const roleType = this.formatRoleName(roleCategory);

      // Calculate metrics for job breakdown
      const totalRequirements =
        (issues.counts.critical || 0) +
        (issues.counts.warning || 0) +
        (issues.counts.passed || 0);
      const mustHaveCount =
        issues.counts.critical + Math.floor(issues.counts.warning / 2);
      const niceToHaveCount = totalRequirements - mustHaveCount;

      // Seniority detection
      const seniorityLevel = this.detectSeniority(job_data.job_title, issues);

      // Build Job Breakdown Card
      this.container.innerHTML = `
                <div class="audit-report audit-report--job-breakdown">
                    <!-- Job Header -->
                    <div class="audit-job-header">
                        <div class="audit-job-header__main">
                            <div class="audit-job-header__company-logo">
                                ${this.getCompanyInitials(job_data.company)}
                            </div>
                            <div class="audit-job-header__info">
                                <h1 class="audit-job-header__title">${this.escapeHtml(
                                  job_data.job_title
                                )}</h1>
                                <div class="audit-job-header__meta">
                                    <span class="audit-job-header__company">${this.escapeHtml(
                                      job_data.company || "Company"
                                    )}</span>
                                    ${
                                      cityName
                                        ? `<span class="audit-job-header__dot">•</span><span class="audit-job-header__location">${
                                            Icons.mapPin
                                          } ${this.escapeHtml(cityName)}</span>`
                                        : ""
                                    }
                                    ${
                                      job_data.salary_display
                                        ? `<span class="audit-job-header__dot">•</span><span class="audit-job-header__salary">${
                                            Icons.dollarSign
                                          } ${this.escapeHtml(
                                            job_data.salary_display
                                          )}</span>`
                                        : ""
                                    }
                                </div>
                            </div>
                        </div>
                        <div class="audit-job-header__actions">
                            <button class="audit-btn audit-btn--primary" data-action="smart-apply">
                                ${Icons.zap} Smart message
                            </button>
                            <button class="audit-btn audit-btn--secondary" data-action="save-job">
                                ${Icons.heart} Save
                            </button>
                        </div>
                    </div>

                    <!-- Key Stats Row -->
                    <div class="audit-job-stats">
                        <div class="audit-job-stat">
                            <div class="audit-job-stat__icon">${
                              Icons.briefcase
                            }</div>
                            <div class="audit-job-stat__content">
                                <div class="audit-job-stat__value">${
                                  seniorityLevel.label
                                }</div>
                                <div class="audit-job-stat__label">Seniority</div>
                            </div>
                        </div>
                        <div class="audit-job-stat">
                            <div class="audit-job-stat__icon">${
                              Icons.target
                            }</div>
                            <div class="audit-job-stat__content">
                                <div class="audit-job-stat__value">${totalRequirements}</div>
                                <div class="audit-job-stat__label">Requirements</div>
                            </div>
                        </div>
                        <div class="audit-job-stat">
                            <div class="audit-job-stat__icon">${
                              Icons.checkCircle
                            }</div>
                            <div class="audit-job-stat__content">
                                <div class="audit-job-stat__value">${mustHaveCount}</div>
                                <div class="audit-job-stat__label">Must-Haves</div>
                            </div>
                        </div>
                        <div class="audit-job-stat">
                            <div class="audit-job-stat__icon">${
                              Icons.star
                            }</div>
                            <div class="audit-job-stat__content">
                                <div class="audit-job-stat__value">${niceToHaveCount}</div>
                                <div class="audit-job-stat__label">Nice-to-Haves</div>
                            </div>
                        </div>
                    </div>

                    <!-- Role Overview -->
                    <div class="audit-section audit-section--overview">
                        <div class="audit-section__header">
                            <h2 class="audit-section__title">${
                              Icons.fileText
                            } Role Overview</h2>
                        </div>
                        <div class="audit-section__body">
                            ${this.renderRoleOverview(
                              job_data,
                              seniorityLevel,
                              roleType
                            )}
                        </div>
                    </div>

                    <!-- Requirements Breakdown - Visual -->
                    <div class="audit-section audit-section--requirements">
                        <div class="audit-section__header">
                            <h2 class="audit-section__title">${
                              Icons.target
                            } What They're Looking For</h2>
                            <span class="audit-section__subtitle">${totalRequirements} requirements identified</span>
                        </div>
                        <div class="audit-section__body">
                            ${this.renderRequirementsBreakdown(
                              issues,
                              passed,
                              category_scores
                            )}
                        </div>
                    </div>

                    <!-- Skills Radar / Bar Chart -->
                    <div class="audit-section audit-section--skills">
                        <div class="audit-section__header">
                            <h2 class="audit-section__title">${
                              Icons.barChart
                            } Skills Required</h2>
                            <span class="audit-section__subtitle">Technical & soft skills breakdown</span>
                        </div>
                        <div class="audit-section__body">
                            ${this.renderSkillsBreakdown(
                              category_scores,
                              issues
                            )}
                        </div>
                    </div>

                    <!-- Compensation Intelligence -->
                    ${this.renderCompensationIntel(
                      job_data,
                      cityName,
                      roleType
                    )}

                    <!-- Interview Battlecard (from enhanced summary) -->
                    ${this.renderInterviewBattlecard(intelligence)}

                    <!-- Application Checklist (from enhanced summary) -->
                    ${this.renderApplicationChecklist(intelligence)}

                    <!-- Questions to Ask (from enhanced summary) -->
                    ${this.renderQuestionsToAsk(intelligence)}

                    <!-- Stand Out Factors (from enhanced summary) -->
                    ${this.renderStandOutFactors(intelligence)}

                    <!-- Career Trajectory (from enhanced summary) -->
                    ${this.renderCareerTrajectory(intelligence)}

                    <!-- Location Intelligence -->
                    ${cityName ? this.renderLocationIntelligence(report) : ""}

                    <!-- More Opportunities -->
                    ${this.renderMoreOpportunities(report)}

                    <!-- Application Toolkit - Full Preview -->
                    <div class="audit-section audit-section--toolkit">
                        ${this.renderApplicationToolkit(report)}
                    </div>

                    <!-- Quick Actions Footer -->
                    <div class="audit-job-footer">
                        <div class="audit-job-footer__left">
                            <button class="audit-btn audit-btn--text" data-action="share">
                                ${Icons.externalLink} Share
                            </button>
                            <button class="audit-btn audit-btn--text" data-action="report-issue">
                                ${Icons.alertCircle} Report Issue
                            </button>
                        </div>
                        <div class="audit-job-footer__right">
                            <button class="audit-btn audit-btn--primary audit-btn--lg" data-action="smart-apply">
                                ${Icons.zap} Get Application Toolkit
                            </button>
                        </div>
                    </div>
                </div>
            `;

      this.attachReportListeners(report);
    }

    // Get company initials for logo placeholder
    getCompanyInitials(company) {
      if (!company) return "?";
      const words = company.split(" ").filter((w) => w.length > 0);
      if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
      }
      return company.substring(0, 2).toUpperCase();
    }

    // Detect seniority level from job title and requirements
    detectSeniority(title, issues) {
      const titleLower = (title || "").toLowerCase();

      if (
        titleLower.includes("director") ||
        titleLower.includes("head of") ||
        titleLower.includes("chief") ||
        titleLower.includes("partner")
      ) {
        return { level: 5, label: "Director+", color: "#7c3aed" };
      }
      if (
        titleLower.includes("vice president") ||
        titleLower.includes("vp") ||
        titleLower.includes("principal")
      ) {
        return { level: 4, label: "VP / Principal", color: "#2563eb" };
      }
      if (
        titleLower.includes("senior") ||
        titleLower.includes("sr.") ||
        titleLower.includes("lead")
      ) {
        return { level: 3, label: "Senior", color: "#059669" };
      }
      if (titleLower.includes("associate") || titleLower.includes("analyst")) {
        return { level: 2, label: "Associate", color: "#d97706" };
      }
      if (
        titleLower.includes("junior") ||
        titleLower.includes("jr.") ||
        titleLower.includes("entry") ||
        titleLower.includes("graduate")
      ) {
        return { level: 1, label: "Entry Level", color: "#6b7280" };
      }

      // Default based on requirement complexity
      const criticalCount = issues?.counts?.critical || 0;
      if (criticalCount > 5)
        return { level: 3, label: "Senior", color: "#059669" };
      if (criticalCount > 2)
        return { level: 2, label: "Mid-Level", color: "#d97706" };
      return { level: 2, label: "Mid-Level", color: "#d97706" };
    }

    // Render role overview section
    renderRoleOverview(job_data, seniority, roleType) {
      const cityName = this.extractCityName(job_data.location || "");

      return `
                <div class="audit-role-overview">
                    <div class="audit-role-overview__grid">
                        <div class="audit-role-overview__item">
                            <span class="audit-role-overview__label">Role Type</span>
                            <span class="audit-role-overview__value">${roleType}</span>
                        </div>
                        <div class="audit-role-overview__item">
                            <span class="audit-role-overview__label">Level</span>
                            <span class="audit-role-overview__value" style="color: ${
                              seniority.color
                            }">${seniority.label}</span>
                        </div>
                        <div class="audit-role-overview__item">
                            <span class="audit-role-overview__label">Location</span>
                            <span class="audit-role-overview__value">${
                              cityName || "Not specified"
                            }</span>
                        </div>
                        <div class="audit-role-overview__item">
                            <span class="audit-role-overview__label">Company</span>
                            <span class="audit-role-overview__value">${this.escapeHtml(
                              job_data.company || "Not specified"
                            )}</span>
                        </div>
                    </div>
                </div>
            `;
    }

    // Render requirements breakdown - visual categorization
    renderRequirementsBreakdown(issues, passed, category_scores) {
      // Combine all requirements into categorized lists
      const mustHaves = issues.critical || [];
      const important = issues.warning || [];
      const niceToHaves = passed.slice(0, 5) || [];

      return `
                <div class="audit-requirements">
                    <!-- Must Haves -->
                    <div class="audit-requirements__category audit-requirements__category--must">
                        <div class="audit-requirements__category-header">
                            <span class="audit-requirements__category-icon">${
                              Icons.alertCircle
                            }</span>
                            <span class="audit-requirements__category-title">Must Have</span>
                            <span class="audit-requirements__category-count">${
                              mustHaves.length
                            }</span>
                        </div>
                        <ul class="audit-requirements__list">
                            ${mustHaves
                              .map(
                                (req) => `
                                <li class="audit-requirements__item audit-requirements__item--critical">
                                    <span class="audit-requirements__bullet"></span>
                                    <span class="audit-requirements__text">${this.escapeHtml(
                                      req.skill_name ||
                                        req.message ||
                                        "Requirement"
                                    )}</span>
                                </li>
                            `
                              )
                              .join("")}
                            ${
                              mustHaves.length === 0
                                ? '<li class="audit-requirements__empty">No critical requirements identified</li>'
                                : ""
                            }
                        </ul>
                    </div>

                    <!-- Important -->
                    <div class="audit-requirements__category audit-requirements__category--important">
                        <div class="audit-requirements__category-header">
                            <span class="audit-requirements__category-icon">${
                              Icons.star
                            }</span>
                            <span class="audit-requirements__category-title">Important</span>
                            <span class="audit-requirements__category-count">${
                              important.length
                            }</span>
                        </div>
                        <ul class="audit-requirements__list">
                            ${important
                              .slice(0, 6)
                              .map(
                                (req) => `
                                <li class="audit-requirements__item audit-requirements__item--warning">
                                    <span class="audit-requirements__bullet"></span>
                                    <span class="audit-requirements__text">${this.escapeHtml(
                                      req.skill_name ||
                                        req.message ||
                                        "Requirement"
                                    )}</span>
                                </li>
                            `
                              )
                              .join("")}
                            ${
                              important.length > 6
                                ? `<li class="audit-requirements__more">+${
                                    important.length - 6
                                  } more</li>`
                                : ""
                            }
                            ${
                              important.length === 0
                                ? '<li class="audit-requirements__empty">No additional requirements</li>'
                                : ""
                            }
                        </ul>
                    </div>

                    <!-- Nice to Have -->
                    <div class="audit-requirements__category audit-requirements__category--nice">
                        <div class="audit-requirements__category-header">
                            <span class="audit-requirements__category-icon">${
                              Icons.checkCircle
                            }</span>
                            <span class="audit-requirements__category-title">Nice to Have</span>
                            <span class="audit-requirements__category-count">${
                              niceToHaves.length
                            }</span>
                        </div>
                        <ul class="audit-requirements__list">
                            ${niceToHaves
                              .map(
                                (req) => `
                                <li class="audit-requirements__item audit-requirements__item--nice">
                                    <span class="audit-requirements__bullet"></span>
                                    <span class="audit-requirements__text">${this.escapeHtml(
                                      req.skill_name ||
                                        req.message ||
                                        "Requirement"
                                    )}</span>
                                </li>
                            `
                              )
                              .join("")}
                            ${
                              niceToHaves.length === 0
                                ? '<li class="audit-requirements__empty">No additional preferences listed</li>'
                                : ""
                            }
                        </ul>
                    </div>
                </div>
            `;
    }

    // Render skills breakdown with visual chart
    renderSkillsBreakdown(category_scores, issues) {
      const categories = Object.values(category_scores || {});

      if (categories.length === 0) {
        return '<p class="audit-empty">Skills breakdown not available</p>';
      }

      return `
                <div class="audit-skills-breakdown">
                    <div class="audit-skills-chart">
                        ${categories
                          .map(
                            (cat) => `
                            <div class="audit-skill-bar">
                                <div class="audit-skill-bar__header">
                                    <span class="audit-skill-bar__name">${this.escapeHtml(
                                      cat.name
                                    )}</span>
                                    <span class="audit-skill-bar__count">${
                                      cat.total || 0
                                    } skills</span>
                                </div>
                                <div class="audit-skill-bar__track">
                                    <div class="audit-skill-bar__fill" style="width: ${Math.min(
                                      cat.percentage || 0,
                                      100
                                    )}%; background: ${this.getSkillBarColor(
                              cat.percentage
                            )}"></div>
                                </div>
                                <div class="audit-skill-bar__skills">
                                    ${(cat.items || [])
                                      .slice(0, 3)
                                      .map(
                                        (skill) => `
                                        <span class="audit-skill-tag">${this.escapeHtml(
                                          skill.name || skill
                                        )}</span>
                                    `
                                      )
                                      .join("")}
                                    ${
                                      (cat.items || []).length > 3
                                        ? `<span class="audit-skill-tag audit-skill-tag--more">+${
                                            cat.items.length - 3
                                          }</span>`
                                        : ""
                                    }
                                </div>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
    }

    getSkillBarColor(percentage) {
      if (percentage >= 80) return "var(--audit-success)";
      if (percentage >= 60) return "var(--audit-accent)";
      if (percentage >= 40) return "var(--audit-warning)";
      return "var(--audit-error)";
    }

    // Render compensation intelligence
    renderCompensationIntel(job_data, cityName, roleType) {
      if (!cityName) return "";

      const compData = this.getCompensationData(cityName, roleType);

      return `
                <div class="audit-section audit-section--compensation">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${
                          Icons.dollarSign
                        } Compensation Insights</h2>
                        <span class="audit-section__subtitle">${roleType} in ${cityName}</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-compensation">
                            <div class="audit-compensation__main">
                                <div class="audit-compensation__salary">
                                    <span class="audit-compensation__label">Typical Base Salary</span>
                                    <span class="audit-compensation__value">${
                                      compData.base
                                    }</span>
                                    <span class="audit-compensation__range">${
                                      compData.range
                                    }</span>
                                </div>
                                <div class="audit-compensation__chart">
                                    ${this.renderSalaryRangeChart(compData)}
                                </div>
                            </div>
                            <div class="audit-compensation__breakdown">
                                <div class="audit-compensation__item">
                                    <span class="audit-compensation__item-label">Bonus</span>
                                    <span class="audit-compensation__item-value">${
                                      compData.bonus
                                    }</span>
                                </div>
                                <div class="audit-compensation__item">
                                    <span class="audit-compensation__item-label">Total Comp</span>
                                    <span class="audit-compensation__item-value">${
                                      compData.total
                                    }</span>
                                </div>
                                <div class="audit-compensation__item">
                                    <span class="audit-compensation__item-label">Tax Rate</span>
                                    <span class="audit-compensation__item-value">${
                                      compData.tax
                                    }</span>
                                </div>
                            </div>
                        </div>
                        <p class="audit-compensation__note">${compData.note}</p>
                    </div>
                </div>
            `;
    }

    getCompensationData(city, roleType) {
      const data = {
        Dubai: {
          base: "AED 350,000",
          range: "AED 250K - 600K",
          bonus: "20-50%",
          total: "AED 420K - 900K",
          tax: "0%",
          note: "Tax-free income. Housing allowance often provided separately.",
          min: 250,
          max: 600,
          typical: 350,
        },
        London: {
          base: "£85,000",
          range: "£60K - £150K",
          bonus: "30-100%",
          total: "£78K - £300K",
          tax: "40-45%",
          note: "Bonus can significantly exceed base at senior levels.",
          min: 60,
          max: 150,
          typical: 85,
        },
        "New York": {
          base: "$145,000",
          range: "$100K - $220K",
          bonus: "50-100%",
          total: "$150K - $440K",
          tax: "35-50%",
          note: "NYC has additional state and city income taxes.",
          min: 100,
          max: 220,
          typical: 145,
        },
        Singapore: {
          base: "SGD 180,000",
          range: "SGD 120K - 300K",
          bonus: "20-40%",
          total: "SGD 144K - 420K",
          tax: "15-22%",
          note: "Low tax jurisdiction. CPF contributions apply for residents.",
          min: 120,
          max: 300,
          typical: 180,
        },
        "Hong Kong": {
          base: "HKD 1,200,000",
          range: "HKD 800K - 2M",
          bonus: "30-60%",
          total: "HKD 1M - 3.2M",
          tax: "15-17%",
          note: "Flat tax rate. High cost of living offsets tax benefits.",
          min: 800,
          max: 2000,
          typical: 1200,
        },
      };

      return (
        data[city] || {
          base: "Competitive",
          range: "Market Rate",
          bonus: "Variable",
          total: "Depends on level",
          tax: "Varies",
          note: "Contact us for specific compensation data for this location.",
          min: 0,
          max: 100,
          typical: 50,
        }
      );
    }

    renderSalaryRangeChart(compData) {
      const { min, max, typical } = compData;
      const range = max - min;
      const typicalPos = ((typical - min) / range) * 100;

      return `
                <div class="audit-salary-chart">
                    <div class="audit-salary-chart__bar">
                        <div class="audit-salary-chart__range"></div>
                        <div class="audit-salary-chart__marker" style="left: ${typicalPos}%">
                            <span class="audit-salary-chart__marker-line"></span>
                            <span class="audit-salary-chart__marker-label">Typical</span>
                        </div>
                    </div>
                    <div class="audit-salary-chart__labels">
                        <span>Min</span>
                        <span>Max</span>
                    </div>
                </div>
            `;
    }

    // Simplified toolkit CTA
    renderToolkitCTA(report) {
      const company = report.job_data?.company || "this company";

      return `
                <div class="audit-toolkit-cta">
                    <div class="audit-toolkit-cta__content">
                        <div class="audit-toolkit-cta__icon">${Icons.zap}</div>
                        <div class="audit-toolkit-cta__text">
                            <h3>Ready to Apply?</h3>
                            <p>Get a tailored cover letter, CV optimization tips, and LinkedIn outreach message for ${this.escapeHtml(
                              company
                            )}</p>
                        </div>
                    </div>
                    <button class="audit-btn audit-btn--primary audit-btn--lg" data-action="smart-apply">
                        ${Icons.download} Get Application Toolkit
                    </button>
                </div>
            `;
    }

    // Chart Rendering Methods

    calculateSkillsMatch(categoryScores) {
      const scores = Object.values(categoryScores);
      if (scores.length === 0) return 0;
      const total = scores.reduce((sum, cat) => sum + cat.percentage, 0);
      return Math.round(total / scores.length);
    }

    getScoreColor(score) {
      if (score >= 75) return "#059669"; // Green
      if (score >= 50) return "#d97706"; // Orange
      return "#dc2626"; // Red
    }

    renderGaugeChart(value, color) {
      const radius = 40;
      const circumference = 2 * Math.PI * radius;
      const offset = circumference - (value / 100) * circumference;

      return `
                <svg class="audit-gauge" viewBox="0 0 100 100">
                    <circle class="audit-gauge__bg" cx="50" cy="50" r="${radius}" />
                    <circle class="audit-gauge__fill" cx="50" cy="50" r="${radius}"
                            style="stroke: ${color}; stroke-dasharray: ${circumference}; stroke-dashoffset: ${offset};"
                            transform="rotate(-90 50 50)" />
                </svg>
            `;
    }

    renderCriticalBars(count) {
      const maxBars = 5;
      const bars = [];
      for (let i = 0; i < maxBars; i++) {
        const isActive = i < count;
        bars.push(
          `<div class="audit-critical-bar ${
            isActive ? "audit-critical-bar--active" : ""
          }"></div>`
        );
      }
      return `<div class="audit-critical-bars">${bars.join("")}</div>`;
    }

    renderSkillsBarChart(categoryScores) {
      const scores = Object.values(categoryScores).slice(0, 4);
      const maxHeight = 50;

      return `
                <div class="audit-mini-bars">
                    ${scores
                      .map(
                        (cat) => `
                        <div class="audit-mini-bar" title="${cat.name}: ${
                          cat.percentage
                        }%">
                            <div class="audit-mini-bar__fill" style="height: ${
                              (cat.percentage / 100) * maxHeight
                            }px; background: ${this.getScoreColor(
                          cat.percentage
                        )}"></div>
                        </div>
                    `
                      )
                      .join("")}
                </div>
            `;
    }

    renderIssuesBarChart(critical, warning) {
      const allIssues = [
        ...critical.map((i) => ({ ...i, type: "critical" })),
        ...warning.slice(0, 3).map((i) => ({ ...i, type: "warning" })),
      ];

      return `
                <div class="audit-issues-bars">
                    ${allIssues
                      .slice(0, 5)
                      .map(
                        (issue, idx) => `
                        <div class="audit-issue-bar">
                            <div class="audit-issue-bar__label">${this.escapeHtml(
                              issue.skill_name ||
                                issue.category ||
                                "Issue " + (idx + 1)
                            )}</div>
                            <div class="audit-issue-bar__track">
                                <div class="audit-issue-bar__gap" style="width: ${
                                  100 - (issue.score || 30)
                                }%"></div>
                                <div class="audit-issue-bar__fill audit-issue-bar__fill--${
                                  issue.type
                                }" style="width: ${issue.score || 30}%"></div>
                            </div>
                            <div class="audit-issue-bar__value">${
                              issue.score || 30
                            }%</div>
                        </div>
                    `
                      )
                      .join("")}
                </div>
            `;
    }

    renderHorizontalBarChart(categoryScores) {
      return `
                <div class="audit-hbar-chart">
                    ${Object.values(categoryScores)
                      .map(
                        (cat) => `
                        <div class="audit-hbar">
                            <div class="audit-hbar__label">${this.escapeHtml(
                              cat.name
                            )}</div>
                            <div class="audit-hbar__container">
                                <div class="audit-hbar__track">
                                    <div class="audit-hbar__fill" style="width: ${
                                      cat.percentage
                                    }%; background: ${this.getScoreColor(
                          cat.percentage
                        )}"></div>
                                    <div class="audit-hbar__benchmark" style="left: 70%"></div>
                                </div>
                                <span class="audit-hbar__value">${
                                  cat.percentage
                                }%</span>
                            </div>
                        </div>
                    `
                      )
                      .join("")}
                    <div class="audit-hbar-legend">
                        <span class="audit-hbar-legend__item">
                            <span class="audit-hbar-legend__line"></span>
                            Benchmark: 70%
                        </span>
                    </div>
                </div>
            `;
    }

    renderCriticalIssue(issue) {
      return `
                <div class="audit-critical-issue">
                    <div class="audit-critical-issue__icon">${
                      Icons.alertCircle
                    }</div>
                    <div class="audit-critical-issue__content">
                        <strong>${this.escapeHtml(
                          issue.skill_name || issue.category
                        )}</strong>
                        <p>${this.escapeHtml(
                          issue.gap_message ||
                            issue.message ||
                            "This is a critical gap that needs to be addressed"
                        )}</p>
                    </div>
                    <div class="audit-critical-issue__action">
                        <button class="audit-btn audit-btn--small" data-action="fix-issue">Fix</button>
                    </div>
                </div>
            `;
    }

    renderWarningIssue(issue) {
      return `
                <div class="audit-warning-issue">
                    <div class="audit-warning-issue__indicator"></div>
                    <div class="audit-warning-issue__content">
                        <span class="audit-warning-issue__title">${this.escapeHtml(
                          issue.skill_name || issue.category
                        )}</span>
                        <span class="audit-warning-issue__score">${
                          issue.score || 40
                        }%</span>
                    </div>
                </div>
            `;
    }

    renderStrengthItem(item) {
      return `
                <div class="audit-strength-item">
                    <div class="audit-strength-item__icon">${Icons.check}</div>
                    <span class="audit-strength-item__text">${this.escapeHtml(
                      item.skill_name || item.question
                    )}</span>
                </div>
            `;
    }

    renderPremiumPreviewCharts() {
      // Fake line chart SVG for premium preview
      return `
                <div class="audit-premium-charts">
                    <div class="audit-premium-chart">
                        <div class="audit-premium-chart__title">Salary Range</div>
                        <svg viewBox="0 0 200 80" class="audit-line-chart">
                            <polyline points="10,60 40,45 80,50 120,30 160,35 190,20" fill="none" stroke="#e2e8f0" stroke-width="2"/>
                            <circle cx="120" cy="30" r="4" fill="#2563eb"/>
                        </svg>
                    </div>
                    <div class="audit-premium-chart">
                        <div class="audit-premium-chart__title">Competition Level</div>
                        <div class="audit-premium-bars-fake">
                            <div style="height: 60%"></div>
                            <div style="height: 80%"></div>
                            <div style="height: 45%"></div>
                            <div style="height: 70%"></div>
                        </div>
                    </div>
                    <div class="audit-premium-chart">
                        <div class="audit-premium-chart__title">Best Apply Time</div>
                        <div class="audit-premium-time-fake">
                            <span>Tue</span>
                            <span>10:00 AM</span>
                        </div>
                    </div>
                </div>
            `;
    }

    renderDonutChart(segments, size = 120) {
      // segments = [{value: 30, color: '#dc2626', label: 'Critical'}, ...]
      const total = segments.reduce((sum, s) => sum + s.value, 0);
      const radius = 40;
      const circumference = 2 * Math.PI * radius;
      let currentOffset = 0;

      const paths = segments
        .map((segment, idx) => {
          const segmentLength = (segment.value / total) * circumference;
          const dashArray = `${segmentLength} ${circumference - segmentLength}`;
          const rotation = (currentOffset / total) * 360 - 90;
          currentOffset += segment.value;

          return `
                    <circle
                        class="audit-donut__segment"
                        cx="50" cy="50" r="${radius}"
                        stroke="${segment.color}"
                        stroke-dasharray="${dashArray}"
                        transform="rotate(${rotation} 50 50)"
                        data-label="${segment.label}"
                        data-value="${segment.value}"
                    />
                `;
        })
        .join("");

      return `
                <svg class="audit-donut" viewBox="0 0 100 100" width="${size}" height="${size}">
                    <circle class="audit-donut__bg" cx="50" cy="50" r="${radius}" />
                    ${paths}
                </svg>
            `;
    }

    renderIssuesPieChart(issues) {
      const critical = issues.counts.critical || 0;
      const warning = issues.counts.warning || 0;
      const passed = issues.counts.passed || 0;
      const total = critical + warning + passed;

      if (total === 0) return "";

      const segments = [];
      if (critical > 0)
        segments.push({ value: critical, color: "#dc2626", label: "Critical" });
      if (warning > 0)
        segments.push({ value: warning, color: "#d97706", label: "Warnings" });
      if (passed > 0)
        segments.push({ value: passed, color: "#059669", label: "Passed" });

      return `
                <div class="audit-pie-container">
                    <div class="audit-pie-chart">
                        ${this.renderDonutChart(segments, 140)}
                        <div class="audit-pie-center">
                            <span class="audit-pie-center__value">${
                              critical + warning
                            }</span>
                            <span class="audit-pie-center__label">Issues</span>
                        </div>
                    </div>
                    <div class="audit-pie-legend">
                        ${segments
                          .map(
                            (s) => `
                            <div class="audit-pie-legend__item">
                                <span class="audit-pie-legend__color" style="background: ${s.color}"></span>
                                <span class="audit-pie-legend__label">${s.label}</span>
                                <span class="audit-pie-legend__value">${s.value}</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
    }

    renderApplicationToolkit(report) {
      const jobTitle = report.job_data?.job_title || "This Role";
      const company = report.job_data?.company || "the company";
      const location = report.job_data?.location || "";
      const cityName = this.extractCityName(location);
      const score = report.health_score || 65;
      const criticalSkills = report.issues?.critical
        ?.slice(0, 3)
        .map((i) => i.skill_name)
        .filter(Boolean) || ["Financial Modeling", "LBO Experience"];
      const strengths = report.issues?.passed
        ?.slice(0, 2)
        .map((i) => i.skill_name)
        .filter(Boolean) || ["Analytical Skills", "Communication"];
      const improvementScore = Math.min(score + 28, 94);
      const todayCount = Math.floor(Math.random() * 30) + 47;

      // Location-specific toolkit title
      const toolkitTitle = cityName
        ? `Your ${cityName} Application Toolkit`
        : "Your Application Toolkit";

      return `
                <div class="audit-toolkit-v2">
                    <!-- Header -->
                    <div class="audit-toolkit-v2__header">
                        <div class="audit-toolkit-v2__badge">
                            ${cityName ? Icons.mapPin : Icons.zap}
                            <span>${
                              cityName
                                ? `${cityName} Market`
                                : "Application Toolkit Ready"
                            }</span>
                        </div>
                        <h2 class="audit-toolkit-v2__title">${toolkitTitle}</h2>
                        <p class="audit-toolkit-v2__subtitle">Tailored specifically for <strong>${jobTitle}</strong> at <strong>${company}</strong>${
        cityName ? ` in <strong>${cityName}</strong>` : ""
      }</p>
                    </div>

                    <!-- Before/After Comparison -->
                    <div class="audit-toolkit-v2__comparison">
                        <div class="audit-toolkit-v2__compare-card audit-toolkit-v2__compare-card--before">
                            <div class="audit-toolkit-v2__compare-label">
                                <span class="audit-toolkit-v2__compare-dot audit-toolkit-v2__compare-dot--red"></span>
                                Without Smart message
                            </div>
                            <div class="audit-toolkit-v2__compare-score">
                                <div class="audit-toolkit-v2__score-circle audit-toolkit-v2__score-circle--low">
                                    <span>${score}%</span>
                                </div>
                                <span class="audit-toolkit-v2__score-label">Match Score</span>
                            </div>
                            <ul class="audit-toolkit-v2__compare-list">
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--negative">
                                    ${Icons.alertCircle}
                                    <span>Generic cover letter</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--negative">
                                    ${Icons.alertCircle}
                                    <span>Missing ATS keywords</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--negative">
                                    ${Icons.alertCircle}
                                    <span>Skill gaps not addressed</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--negative">
                                    ${Icons.alertCircle}
                                    <span>~15% response rate</span>
                                </li>
                            </ul>
                        </div>

                        <div class="audit-toolkit-v2__compare-arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </div>

                        <div class="audit-toolkit-v2__compare-card audit-toolkit-v2__compare-card--after">
                            <div class="audit-toolkit-v2__compare-label">
                                <span class="audit-toolkit-v2__compare-dot audit-toolkit-v2__compare-dot--green"></span>
                                With Smart message
                            </div>
                            <div class="audit-toolkit-v2__compare-score">
                                <div class="audit-toolkit-v2__score-circle audit-toolkit-v2__score-circle--high">
                                    <span>${improvementScore}%</span>
                                </div>
                                <span class="audit-toolkit-v2__score-label">Match Score</span>
                            </div>
                            <ul class="audit-toolkit-v2__compare-list">
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--positive">
                                    ${Icons.check}
                                    <span>Tailored cover letter</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--positive">
                                    ${Icons.check}
                                    <span>ATS-optimized CV</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--positive">
                                    ${Icons.check}
                                    <span>Gaps addressed in letter</span>
                                </li>
                                <li class="audit-toolkit-v2__compare-item audit-toolkit-v2__compare-item--positive">
                                    ${Icons.check}
                                    <span>~47% response rate</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- What's Included -->
                    <div class="audit-toolkit-v2__included">
                        <h3 class="audit-toolkit-v2__section-title">What's in Your Toolkit</h3>

                        <div class="audit-toolkit-v2__items">
                            <!-- Cover Letter -->
                            <div class="audit-toolkit-v2__item">
                                <div class="audit-toolkit-v2__item-preview">
                                    <div class="audit-toolkit-v2__doc">
                                        <div class="audit-toolkit-v2__doc-header"></div>
                                        <div class="audit-toolkit-v2__doc-line audit-toolkit-v2__doc-line--title"></div>
                                        <div class="audit-toolkit-v2__doc-line"></div>
                                        <div class="audit-toolkit-v2__doc-line"></div>
                                        <div class="audit-toolkit-v2__doc-line audit-toolkit-v2__doc-line--short"></div>
                                        <div class="audit-toolkit-v2__doc-line"></div>
                                        <div class="audit-toolkit-v2__doc-highlight">
                                            <span>Addresses: ${
                                              criticalSkills[0] || "Key Skills"
                                            }</span>
                                        </div>
                                        <div class="audit-toolkit-v2__doc-line"></div>
                                        <div class="audit-toolkit-v2__doc-line audit-toolkit-v2__doc-line--short"></div>
                                    </div>
                                </div>
                                <div class="audit-toolkit-v2__item-info">
                                    <h4>Tailored Cover Letter</h4>
                                    <p>Professionally written letter that addresses your skill gaps and highlights why you're the right fit for ${jobTitle}</p>
                                    <div class="audit-toolkit-v2__item-tags">
                                        <span>Role-specific</span>
                                        <span>Gap-addressed</span>
                                        <span>Ready to send</span>
                                    </div>
                                </div>
                            </div>

                            <!-- CV Optimization -->
                            <div class="audit-toolkit-v2__item">
                                <div class="audit-toolkit-v2__item-preview">
                                    <div class="audit-toolkit-v2__cv">
                                        <div class="audit-toolkit-v2__cv-section">
                                            <span class="audit-toolkit-v2__cv-label">Add Keywords</span>
                                            <div class="audit-toolkit-v2__cv-keywords">
                                                ${criticalSkills
                                                  .slice(0, 3)
                                                  .map(
                                                    (s) =>
                                                      `<span class="audit-toolkit-v2__keyword audit-toolkit-v2__keyword--add">+ ${s}</span>`
                                                  )
                                                  .join("")}
                                            </div>
                                        </div>
                                        <div class="audit-toolkit-v2__cv-section">
                                            <span class="audit-toolkit-v2__cv-label">Emphasize</span>
                                            <div class="audit-toolkit-v2__cv-keywords">
                                                ${strengths
                                                  .map(
                                                    (s) =>
                                                      `<span class="audit-toolkit-v2__keyword audit-toolkit-v2__keyword--highlight">★ ${s}</span>`
                                                  )
                                                  .join("")}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="audit-toolkit-v2__item-info">
                                    <h4>CV Optimization Guide</h4>
                                    <p>Specific keywords and phrases to add to your CV to pass ATS screening and match this job's requirements</p>
                                    <div class="audit-toolkit-v2__item-tags">
                                        <span>ATS-optimized</span>
                                        <span>Keyword-matched</span>
                                        <span>Section-by-section</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Networking Message -->
                            <div class="audit-toolkit-v2__item">
                                <div class="audit-toolkit-v2__item-preview">
                                    <div class="audit-toolkit-v2__message">
                                        <div class="audit-toolkit-v2__message-header">
                                            <div class="audit-toolkit-v2__message-avatar">
                                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                                            </div>
                                            <div class="audit-toolkit-v2__message-to">To: Hiring Manager</div>
                                        </div>
                                        <div class="audit-toolkit-v2__message-body">
                                            <div class="audit-toolkit-v2__message-text">Hi, I noticed ${company}'s ${jobTitle} role...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="audit-toolkit-v2__item-info">
                                    <h4>LinkedIn Outreach Message</h4>
                                    <p>Professional networking message to connect with hiring managers and recruiters at ${company}</p>
                                    <div class="audit-toolkit-v2__item-tags">
                                        <span>LinkedIn-ready</span>
                                        <span>Professional tone</span>
                                        <span>Personalized</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Proof + CTA -->
                    <div class="audit-toolkit-v2__cta-section">
                        <div class="audit-toolkit-v2__social-proof">
                            <div class="audit-toolkit-v2__avatars">
                                <div class="audit-toolkit-v2__avatar" style="background: #6366f1;">J</div>
                                <div class="audit-toolkit-v2__avatar" style="background: #8b5cf6;">M</div>
                                <div class="audit-toolkit-v2__avatar" style="background: #ec4899;">S</div>
                                <div class="audit-toolkit-v2__avatar" style="background: #14b8a6;">A</div>
                                <div class="audit-toolkit-v2__avatar audit-toolkit-v2__avatar--more">+${todayCount}</div>
                            </div>
                            <p><strong>${todayCount} candidates</strong> used Smart message today</p>
                        </div>

                        <button class="audit-toolkit-v2__cta" data-action="smart-apply">
                            <span class="audit-toolkit-v2__cta-icon">${
                              Icons.download
                            }</span>
                            <span class="audit-toolkit-v2__cta-text">
                                <strong>Get Your Application Toolkit</strong>
                                <small>Cover letter + CV guide + Networking message</small>
                            </span>
                            <span class="audit-toolkit-v2__cta-arrow">→</span>
                        </button>

                        <p class="audit-toolkit-v2__guarantee">
                            ${Icons.check} Ready in 30 seconds • ${
        Icons.check
      } Personalized for this role • ${Icons.check} Edit anytime
                        </p>
                    </div>
                </div>
            `;
    }

    // More Opportunities CTA - location specific
    renderMoreOpportunities(report) {
      const location = report.job_data?.location || "";
      const cityName = this.extractCityName(location);
      const roleCategory = report.job_data?.role_category || "finance";
      const roleType = this.formatRoleName(roleCategory);

      // Don't render if no location
      if (!cityName) return "";

      // Generate realistic opportunity count based on city
      const opportunityCounts = {
        Dubai: 45,
        London: 120,
        "New York": 95,
        Singapore: 38,
        "Hong Kong": 52,
        Dublin: 28,
        "Abu Dhabi": 22,
        Riyadh: 18,
        Frankfurt: 35,
        Paris: 42,
        Sydney: 30,
        Mumbai: 55,
      };
      const count =
        opportunityCounts[cityName] || Math.floor(Math.random() * 30) + 15;

      return `
                <div class="audit-section audit-section--opportunities">
                    <div class="audit-opportunities">
                        <div class="audit-opportunities__icon">
                            ${Icons.mapPin}
                        </div>
                        <div class="audit-opportunities__content">
                            <h3 class="audit-opportunities__title">
                                ${count}+ ${roleType} roles in ${cityName}
                            </h3>
                            <p class="audit-opportunities__subtitle">
                                Discover more opportunities matching your profile in the ${cityName} market
                            </p>
                        </div>
                        <a href="/jobs/?location=${encodeURIComponent(
                          cityName
                        )}&category=${encodeURIComponent(
        roleCategory
      )}" class="audit-opportunities__btn">
                            Browse ${cityName} Jobs
                            ${Icons.arrowRight}
                        </a>
                    </div>
                </div>
            `;
    }

    // Location Intelligence Section
    renderLocationIntelligence(report) {
      const location = report.job_data?.location || "";
      const cityName = this.extractCityName(location);
      const roleCategory = report.job_data?.role_category || "finance";
      const roleType = this.formatRoleName(roleCategory);

      // Don't render if no location
      if (!cityName) return "";

      // Location-specific data
      const locationData = this.getLocationData(cityName);

      return `
                <div class="audit-section audit-section--location-intel">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">
                            ${Icons.globe}
                            ${cityName} Market Insights
                        </h2>
                        <span class="audit-section__subtitle">What you need to know</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-location-intel">
                            <!-- Salary Benchmark -->
                            <div class="audit-location-intel__card">
                                <div class="audit-location-intel__icon">${Icons.dollarSign}</div>
                                <div class="audit-location-intel__label">Typical ${roleType} Salary</div>
                                <div class="audit-location-intel__value">${locationData.salary}</div>
                                <div class="audit-location-intel__note">${locationData.salaryNote}</div>
                            </div>

                            <!-- Cost of Living -->
                            <div class="audit-location-intel__card">
                                <div class="audit-location-intel__icon">${Icons.home}</div>
                                <div class="audit-location-intel__label">Cost of Living</div>
                                <div class="audit-location-intel__value">${locationData.costOfLiving}</div>
                                <div class="audit-location-intel__note">${locationData.costNote}</div>
                            </div>

                            <!-- Work Culture -->
                            <div class="audit-location-intel__card">
                                <div class="audit-location-intel__icon">${Icons.briefcase}</div>
                                <div class="audit-location-intel__label">Work Culture</div>
                                <div class="audit-location-intel__value">${locationData.culture}</div>
                                <div class="audit-location-intel__note">${locationData.cultureNote}</div>
                            </div>

                            <!-- Visa/Sponsorship -->
                            <div class="audit-location-intel__card">
                                <div class="audit-location-intel__icon">${Icons.passport}</div>
                                <div class="audit-location-intel__label">Work Authorization</div>
                                <div class="audit-location-intel__value">${locationData.visa}</div>
                                <div class="audit-location-intel__note">${locationData.visaNote}</div>
                            </div>
                        </div>

                        <!-- Relocation CTA -->
                        <div class="audit-location-intel__cta">
                            <p>Considering relocating to ${cityName}? Our team can help with visa guidance, cost comparisons, and connecting you with the right opportunities.</p>
                            <button class="audit-btn audit-btn--secondary" data-action="relocation-consult">
                                ${Icons.plane}
                                Get Relocation Support
                            </button>
                        </div>
                    </div>
                </div>
            `;
    }

    // Location data helper
    getLocationData(city) {
      const data = {
        Dubai: {
          salary: "AED 300-600K",
          salaryNote: "Tax-free income",
          costOfLiving: "High",
          costNote: "Housing is the main expense",
          culture: "International",
          cultureNote: "Fast-paced, relationship-driven",
          visa: "Employer Sponsored",
          visaNote: "Most firms sponsor readily",
        },
        London: {
          salary: "£65-150K",
          salaryNote: "Plus bonus (50-100%)",
          costOfLiving: "Very High",
          costNote: "Zone 1-2 rent £2-3K/month",
          culture: "Traditional",
          cultureNote: "Established hierarchies",
          visa: "Skilled Worker Visa",
          visaNote: "Points-based system",
        },
        "New York": {
          salary: "$120-250K",
          salaryNote: "Base + bonus structure",
          costOfLiving: "Very High",
          costNote: "Manhattan rent $3-5K/month",
          culture: "Competitive",
          cultureNote: "Results-driven environment",
          visa: "H-1B / L-1",
          visaNote: "Lottery system for H-1B",
        },
        Singapore: {
          salary: "SGD 150-350K",
          salaryNote: "Low tax environment",
          costOfLiving: "High",
          costNote: "Housing subsidy common",
          culture: "Meritocratic",
          cultureNote: "Asia-Pacific hub",
          visa: "Employment Pass",
          visaNote: "Straightforward for finance",
        },
        "Hong Kong": {
          salary: "HKD 800K-2M",
          salaryNote: "Low flat tax rate",
          costOfLiving: "Very High",
          costNote: "World's priciest housing",
          culture: "Fast-paced",
          cultureNote: "China gateway",
          visa: "Employment Visa",
          visaNote: "Employer-sponsored",
        },
        Dublin: {
          salary: "€70-140K",
          salaryNote: "Tech/finance hub",
          costOfLiving: "High",
          costNote: "Housing shortage",
          culture: "Relaxed",
          cultureNote: "Good work-life balance",
          visa: "Critical Skills Permit",
          visaNote: "Finance roles qualify",
        },
      };

      return (
        data[city] || {
          salary: "Competitive",
          salaryNote: "Market rate",
          costOfLiving: "Varies",
          costNote: "Research recommended",
          culture: "Professional",
          cultureNote: "Industry standard",
          visa: "Check Requirements",
          visaNote: "Depends on nationality",
        }
      );
    }

    // Enhanced Summary Sections (from AI-generated job analysis)

    renderInterviewBattlecard(intelligence) {
      const battlecard = intelligence?.interview_battlecard;
      if (!battlecard || !battlecard.has_data) return "";

      return `
                <div class="audit-section audit-section--battlecard">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${Icons.target} ${
        battlecard.title
      }</h2>
                        <span class="audit-section__subtitle">${
                          battlecard.subtitle
                        }</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-battlecard">
                            ${(battlecard.stages || [])
                              .map(
                                (stage, i) => `
                                <div class="audit-battlecard__stage">
                                    <div class="audit-battlecard__stage-header">
                                        <span class="audit-battlecard__stage-number">${
                                          i + 1
                                        }</span>
                                        <h4 class="audit-battlecard__stage-name">${this.escapeHtml(
                                          stage.name
                                        )}</h4>
                                    </div>
                                    <p class="audit-battlecard__stage-focus">${this.escapeHtml(
                                      stage.focus
                                    )}</p>
                                    ${
                                      stage.tips
                                        ? `
                                        <ul class="audit-battlecard__tips">
                                            ${stage.tips
                                              .map(
                                                (tip) =>
                                                  `<li>${this.escapeHtml(
                                                    tip
                                                  )}</li>`
                                              )
                                              .join("")}
                                        </ul>
                                    `
                                        : ""
                                    }
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                        ${
                          battlecard.likely_questions?.length > 0
                            ? `
                            <div class="audit-battlecard__questions">
                                <h4>Likely Interview Questions</h4>
                                <ul>
                                    ${battlecard.likely_questions
                                      .slice(0, 5)
                                      .map(
                                        (q) => `<li>${this.escapeHtml(q)}</li>`
                                      )
                                      .join("")}
                                </ul>
                            </div>
                        `
                            : ""
                        }
                    </div>
                </div>
            `;
    }

    renderApplicationChecklist(intelligence) {
      const checklist = intelligence?.application_checklist;
      if (!checklist) return "";

      return `
                <div class="audit-section audit-section--checklist">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${Icons.checkCircle} ${
        checklist.title
      }</h2>
                        <span class="audit-section__subtitle">${
                          checklist.subtitle
                        }</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-checklist">
                            ${(checklist.items || [])
                              .map(
                                (item, i) => `
                                <label class="audit-checklist__item audit-checklist__item--${
                                  item.priority || "medium"
                                }">
                                    <input type="checkbox" id="checklist-${i}">
                                    <span class="audit-checklist__checkbox"></span>
                                    <span class="audit-checklist__text">${this.escapeHtml(
                                      item.task || item
                                    )}</span>
                                    ${
                                      item.priority === "high"
                                        ? `<span class="audit-checklist__priority">Priority</span>`
                                        : ""
                                    }
                                </label>
                            `
                              )
                              .join("")}
                        </div>
                        <div class="audit-checklist__progress">
                            <div class="audit-checklist__progress-bar">
                                <div class="audit-checklist__progress-fill" style="width: 0%"></div>
                            </div>
                            <span class="audit-checklist__progress-text">0 of ${
                              checklist.items?.length || 0
                            } completed</span>
                        </div>
                    </div>
                </div>
            `;
    }

    renderQuestionsToAsk(intelligence) {
      const questions = intelligence?.questions_to_ask;
      if (!questions || !questions.questions?.length) return "";

      return `
                <div class="audit-section audit-section--questions">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${
                          Icons.messageSquare || Icons.fileText
                        } ${questions.title}</h2>
                        <span class="audit-section__subtitle">${
                          questions.subtitle
                        }</span>
                    </div>
                    <div class="audit-section__body">
                        <ul class="audit-questions-list">
                            ${questions.questions
                              .map(
                                (q) => `
                                <li class="audit-questions-list__item">
                                    <span class="audit-questions-list__icon">?</span>
                                    <span class="audit-questions-list__text">${this.escapeHtml(
                                      q
                                    )}</span>
                                    <button class="audit-questions-list__copy" data-copy="${this.escapeHtml(
                                      q
                                    )}" title="Copy question">
                                        ${Icons.copy}
                                    </button>
                                </li>
                            `
                              )
                              .join("")}
                        </ul>
                    </div>
                </div>
            `;
    }

    renderStandOutFactors(intelligence) {
      const factors = intelligence?.stand_out_factors;
      if (!factors || !factors.has_data) return "";

      return `
                <div class="audit-section audit-section--stand-out">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${Icons.star} ${
        factors.title
      }</h2>
                        <span class="audit-section__subtitle">${
                          factors.subtitle
                        }</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-stand-out">
                            ${(factors.factors || [])
                              .map(
                                (factor, i) => `
                                <div class="audit-stand-out__item">
                                    <span class="audit-stand-out__number">${
                                      i + 1
                                    }</span>
                                    <div class="audit-stand-out__content">
                                        <h4>${this.escapeHtml(
                                          factor.title || factor
                                        )}</h4>
                                        ${
                                          factor.description
                                            ? `<p>${this.escapeHtml(
                                                factor.description
                                              )}</p>`
                                            : ""
                                        }
                                    </div>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                    </div>
                </div>
            `;
    }

    renderCareerTrajectory(intelligence) {
      const trajectory = intelligence?.career_trajectory;
      if (!trajectory || !trajectory.has_data) return "";

      const steps = trajectory.steps || [];

      return `
                <div class="audit-section audit-section--trajectory">
                    <div class="audit-section__header">
                        <h2 class="audit-section__title">${Icons.trending} ${
        trajectory.title
      }</h2>
                        <span class="audit-section__subtitle">${
                          trajectory.subtitle
                        }</span>
                    </div>
                    <div class="audit-section__body">
                        <div class="audit-trajectory">
                            ${steps
                              .map(
                                (step, i) => `
                                <div class="audit-trajectory__step ${
                                  i === 0
                                    ? "audit-trajectory__step--current"
                                    : ""
                                }">
                                    <div class="audit-trajectory__marker"></div>
                                    <div class="audit-trajectory__content">
                                        <h4>${this.escapeHtml(
                                          step.role || step.title || step
                                        )}</h4>
                                        ${
                                          step.timeline
                                            ? `<span class="audit-trajectory__timeline">${this.escapeHtml(
                                                step.timeline
                                              )}</span>`
                                            : ""
                                        }
                                        ${
                                          step.description
                                            ? `<p>${this.escapeHtml(
                                                step.description
                                              )}</p>`
                                            : ""
                                        }
                                    </div>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                    </div>
                </div>
            `;
    }

    // Section Renderers for McKinsey Report

    renderProfileBreakdown(category_scores) {
      return `
                <div class="audit-card">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.barChart
                        } Profile Breakdown</h3>
                    </div>
                    <div class="audit-card__body">
                        ${Object.values(category_scores)
                          .map(
                            (cat) => `
                            <div class="audit-bar">
                                <div class="audit-bar__header">
                                    <span class="audit-bar__name">${this.escapeHtml(
                                      cat.name
                                    )}</span>
                                    <span class="audit-bar__value">${
                                      cat.percentage
                                    }%</span>
                                </div>
                                <div class="audit-bar__track">
                                    <div class="audit-bar__fill" style="width: ${
                                      cat.percentage
                                    }%; background: ${this.getScoreGradient(
                              cat.percentage
                            )}"></div>
                                </div>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;
    }

    renderIssuesSection(issues, passed) {
      const hasIssues = issues.critical.length > 0 || issues.warning.length > 0;

      return `
                ${
                  hasIssues
                    ? `
                <div class="audit-card">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.alertCircle
                        } Areas to Address</h3>
                        <span class="audit-card__badge audit-card__badge--warning">${
                          issues.counts.critical + issues.counts.warning
                        } items</span>
                    </div>
                    <div class="audit-card__body audit-card__body--list">
                        ${issues.critical
                          .map((issue) => this.renderIssue(issue, "critical"))
                          .join("")}
                        ${issues.warning
                          .map((issue) => this.renderIssue(issue, "warning"))
                          .join("")}
                    </div>
                </div>
                `
                    : ""
                }

                ${
                  passed.length > 0
                    ? `
                <div class="audit-card">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.checkCircle
                        } Your Strengths</h3>
                    </div>
                    <div class="audit-card__body audit-card__body--strengths">
                        ${passed
                          .slice(0, 5)
                          .map(
                            (item) => `
                            <div class="audit-strength">
                                <span class="audit-strength__icon">${
                                  Icons.check
                                }</span>
                                <span class="audit-strength__text">${this.escapeHtml(
                                  item.skill_name || item.question
                                )}</span>
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                </div>
                `
                    : ""
                }
            `;
    }

    renderKeywordsSection(keywords) {
      return `
                <div class="audit-card audit-card--keywords">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.key
                        } ATS-Critical Keywords</h3>
                        <span class="audit-card__badge">Include in CV</span>
                    </div>
                    <div class="audit-card__body">
                        <p class="audit-card__subtitle">${this.escapeHtml(
                          keywords.subtitle
                        )}</p>

                        ${
                          keywords.keywords.must_have?.length > 0
                            ? `
                        <div class="audit-keywords__group">
                            <h4 class="audit-keywords__label audit-keywords__label--critical">Must Have</h4>
                            <div class="audit-keywords__list">
                                ${keywords.keywords.must_have
                                  .map(
                                    (k) => `
                                    <span class="audit-keyword audit-keyword--critical" title="${this.escapeHtml(
                                      k.frequency
                                    )}">
                                        ${this.escapeHtml(k.term)}
                                        ${
                                          k.ats_critical
                                            ? '<span class="audit-keyword__flag">ATS</span>'
                                            : ""
                                        }
                                    </span>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                        `
                            : ""
                        }

                        ${
                          keywords.keywords.should_have?.length > 0
                            ? `
                        <div class="audit-keywords__group">
                            <h4 class="audit-keywords__label">Should Have</h4>
                            <div class="audit-keywords__list">
                                ${keywords.keywords.should_have
                                  .map(
                                    (k) => `
                                    <span class="audit-keyword">${this.escapeHtml(
                                      k.term
                                    )}</span>
                                `
                                  )
                                  .join("")}
                            </div>
                        </div>
                        `
                            : ""
                        }

                        <div class="audit-tip">
                            <strong>Pro tip:</strong> ${this.escapeHtml(
                              keywords.ats_tip
                            )}
                        </div>
                    </div>
                </div>
            `;
    }

    renderCoverLetterSection(coverLetter) {
      return `
                <div class="audit-card audit-card--cover-letter">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.fileText
                        } Tailored Cover Letter</h3>
                        <span class="audit-card__badge">${
                          coverLetter.word_count_estimate
                        }</span>
                    </div>
                    <div class="audit-card__body">
                        <div class="audit-preview">
                            <pre class="audit-preview__text">${this.escapeHtml(
                              coverLetter.preview
                            )}</pre>
                            <div class="audit-preview__fade"></div>
                        </div>
                        <div class="audit-preview__sections">
                            ${coverLetter.sections
                              .map(
                                (s) =>
                                  `<span class="audit-preview__section">${this.escapeHtml(
                                    s
                                  )}</span>`
                              )
                              .join("")}
                        </div>
                        <button class="audit-btn audit-btn--generate" data-action="generate-cover-letter">
                            ${Icons.zap}
                            Generate Full Cover Letter
                        </button>
                    </div>
                </div>
            `;
    }

    renderCVTemplateSection(cvTemplate) {
      return `
                <div class="audit-card audit-card--cv">
                    <div class="audit-card__header">
                        <h3 class="audit-card__title">${
                          Icons.fileText
                        } Optimized CV Template</h3>
                        <span class="audit-card__badge">${
                          cvTemplate.format
                        }</span>
                    </div>
                    <div class="audit-card__body">
                        <p class="audit-card__subtitle">${this.escapeHtml(
                          cvTemplate.subtitle
                        )}</p>

                        <div class="audit-cv-sections">
                            ${cvTemplate.sections
                              .map(
                                (s) => `
                                <div class="audit-cv-section ${
                                  s.priority === "high"
                                    ? "audit-cv-section--priority"
                                    : ""
                                }">
                                    <span class="audit-cv-section__name">${this.escapeHtml(
                                      s.name
                                    )}</span>
                                    <span class="audit-cv-section__lines">${this.escapeHtml(
                                      s.lines
                                    )}</span>
                                </div>
                            `
                              )
                              .join("")}
                        </div>

                        <div class="audit-cv-actions">
                            <button class="audit-btn audit-btn--generate" data-action="generate-cv">
                                ${Icons.download}
                                Generate CV Template
                            </button>
                            <div class="audit-cv-formats">
                                ${cvTemplate.download_formats
                                  .map(
                                    (f) =>
                                      `<span class="audit-cv-format">${f}</span>`
                                  )
                                  .join("")}
                            </div>
                        </div>
                    </div>
                </div>
            `;
    }

    renderTimingSection(timing) {
      const urgencyClasses = {
        high: "audit-timing--urgent",
        moderate: "audit-timing--moderate",
        low: "audit-timing--low",
      };

      return `
                <div class="audit-sidebar-card ${
                  urgencyClasses[timing.urgency] || ""
                }">
                    <div class="audit-sidebar-card__header">
                        <h4 class="audit-sidebar-card__title">${
                          Icons.clock
                        } Application Timing</h4>
                    </div>
                    <div class="audit-sidebar-card__body">
                        <div class="audit-timing__urgency audit-timing__urgency--${
                          timing.urgency
                        }">
                            <span class="audit-timing__days">${
                              timing.days_posted
                            }</span>
                            <span class="audit-timing__label">days posted</span>
                        </div>
                        <p class="audit-timing__message">${this.escapeHtml(
                          timing.urgency_message
                        )}</p>

                        <div class="audit-timing__best">
                            <h5>Best times to apply:</h5>
                            ${timing.best_times
                              .slice(0, 2)
                              .map(
                                (t) => `
                                <div class="audit-timing__slot">
                                    <span>${t.day}, ${t.time}</span>
                                    <span class="audit-timing__effectiveness">${t.effectiveness}%</span>
                                </div>
                            `
                              )
                              .join("")}
                        </div>

                        <div class="audit-timing__season">
                            <span class="audit-timing__season-label">Current hiring season:</span>
                            <span class="audit-timing__season-value audit-timing__season-value--${timing.current_season.season.toLowerCase()}">${
        timing.current_season.season
      }</span>
                            <p class="audit-timing__season-note">${this.escapeHtml(
                              timing.current_season.note
                            )}</p>
                        </div>
                    </div>
                </div>
            `;
    }

    renderLocationsSection(locations) {
      return `
                <div class="audit-sidebar-card">
                    <div class="audit-sidebar-card__header">
                        <h4 class="audit-sidebar-card__title">${
                          Icons.globe
                        } Best Markets</h4>
                    </div>
                    <div class="audit-sidebar-card__body">
                        <div class="audit-locations">
                            ${locations.locations
                              .slice(0, 4)
                              .map(
                                (loc, idx) => `
                                <div class="audit-location ${
                                  loc.is_current
                                    ? "audit-location--current"
                                    : ""
                                }">
                                    <span class="audit-location__rank">${
                                      idx + 1
                                    }</span>
                                    <div class="audit-location__info">
                                        <span class="audit-location__city">${this.escapeHtml(
                                          loc.city
                                        )}</span>
                                        <span class="audit-location__meta">${
                                          loc.jobs_available
                                        } jobs • ${loc.avg_salary}</span>
                                    </div>
                                    <div class="audit-location__score">
                                        <div class="audit-location__bar" style="width: ${
                                          loc.score
                                        }%"></div>
                                    </div>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                        <p class="audit-sidebar-card__insight">${this.escapeHtml(
                          locations.insight
                        )}</p>
                    </div>
                </div>
            `;
    }

    renderNetworkingSection(networking) {
      return `
                <div class="audit-sidebar-card">
                    <div class="audit-sidebar-card__header">
                        <h4 class="audit-sidebar-card__title">${
                          Icons.users
                        } Referral Strategy</h4>
                    </div>
                    <div class="audit-sidebar-card__body">
                        <div class="audit-contacts">
                            ${networking.contacts
                              .map(
                                (c) => `
                                <div class="audit-contact">
                                    <span class="audit-contact__role">${this.escapeHtml(
                                      c.role
                                    )}</span>
                                    <span class="audit-contact__why">${this.escapeHtml(
                                      c.why
                                    )}</span>
                                </div>
                            `
                              )
                              .join("")}
                        </div>

                        <div class="audit-templates">
                            <button class="audit-template-btn" data-action="generate-email">
                                ${Icons.mail}
                                <span>Email Template</span>
                            </button>
                            <button class="audit-template-btn" data-action="generate-linkedin">
                                ${Icons.linkedin}
                                <span>LinkedIn Message</span>
                            </button>
                        </div>

                        <p class="audit-tip audit-tip--small">${this.escapeHtml(
                          networking.tip
                        )}</p>
                    </div>
                </div>
            `;
    }

    renderCompaniesSection(companies) {
      return `
                <div class="audit-sidebar-card">
                    <div class="audit-sidebar-card__header">
                        <h4 class="audit-sidebar-card__title">${
                          Icons.building
                        } Companies to Consider</h4>
                    </div>
                    <div class="audit-sidebar-card__body">
                        <div class="audit-companies">
                            ${companies.companies
                              .slice(0, 5)
                              .map(
                                (c) => `
                                <div class="audit-company">
                                    <div class="audit-company__info">
                                        <span class="audit-company__name">${this.escapeHtml(
                                          c.name
                                        )}</span>
                                        <span class="audit-company__type">${this.escapeHtml(
                                          c.type
                                        )}</span>
                                    </div>
                                    <div class="audit-company__score" title="Hiring activity score">
                                        <div class="audit-company__bar" style="width: ${
                                          c.hiring_score
                                        }%"></div>
                                        <span>${c.hiring_score}</span>
                                    </div>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                        <p class="audit-sidebar-card__insight">${this.escapeHtml(
                          companies.insight
                        )}</p>
                    </div>
                </div>
            `;
    }

    renderPerfectRolesSection(perfectRoles) {
      return `
                <div class="audit-sidebar-card">
                    <div class="audit-sidebar-card__header">
                        <h4 class="audit-sidebar-card__title">${
                          Icons.target
                        } Similar Roles</h4>
                    </div>
                    <div class="audit-sidebar-card__body">
                        <div class="audit-roles">
                            ${perfectRoles.roles
                              .slice(0, 3)
                              .map(
                                (r) => `
                                <a href="${this.escapeHtml(
                                  r.url
                                )}" class="audit-role" target="_blank">
                                    <span class="audit-role__title">${this.escapeHtml(
                                      r.title
                                    )}</span>
                                    <span class="audit-role__company">${this.escapeHtml(
                                      r.company
                                    )} • ${this.escapeHtml(r.location)}</span>
                                </a>
                            `
                              )
                              .join("")}
                        </div>
                        <a href="${this.escapeHtml(
                          perfectRoles.view_all_url
                        )}" class="audit-sidebar-card__link">
                            View all similar roles ${Icons.arrowRight}
                        </a>
                    </div>
                </div>
            `;
    }

    renderIssue(issue, severity) {
      const severityLabels = {
        critical: "Critical",
        warning: "Attention",
        notice: "Note",
      };
      return `
                <div class="audit-issue audit-issue--${severity}">
                    <div class="audit-issue__indicator"></div>
                    <div class="audit-issue__content">
                        <div class="audit-issue__header">
                            <span class="audit-issue__name">${this.escapeHtml(
                              issue.skill_name || issue.category
                            )}</span>
                            <span class="audit-issue__severity">${
                              severityLabels[severity]
                            }</span>
                        </div>
                        <p class="audit-issue__message">${this.escapeHtml(
                          issue.message
                        )}</p>
                    </div>
                </div>
            `;
    }

    getScoreGradient(score) {
      if (score >= 75)
        return "linear-gradient(90deg, #2D6A4F 0%, #3D8B68 100%)";
      if (score >= 50)
        return "linear-gradient(90deg, #B08D57 0%, #CB997E 100%)";
      return "linear-gradient(90deg, #D32F2F 0%, #E57373 100%)";
    }

    attachReportListeners(report) {
      // Smart message buttons
      this.container
        .querySelectorAll('[data-action="smart-apply"]')
        .forEach((btn) => {
          btn.addEventListener("click", () => this.initiateSmartApply(report));
        });

      // Direct Apply buttons - simple redirect, no guilt-tripping
      this.container
        .querySelectorAll('[data-action="apply-direct"]')
        .forEach((btn) => {
          btn.addEventListener("click", () => {
            window.open(report.job_data.application_url || "#", "_blank");
          });
        });

      // Generate Cover Letter
      this.container
        .querySelector('[data-action="generate-cover-letter"]')
        ?.addEventListener("click", (e) => {
          this.generateContent("cover_letter", e.target);
        });

      // Generate CV Template
      this.container
        .querySelector('[data-action="generate-cv"]')
        ?.addEventListener("click", (e) => {
          this.generateContent("cv_template", e.target);
        });

      // Generate Email Template
      this.container
        .querySelector('[data-action="generate-email"]')
        ?.addEventListener("click", (e) => {
          this.generateContent("email_template", e.target);
        });

      // Generate LinkedIn Message
      this.container
        .querySelector('[data-action="generate-linkedin"]')
        ?.addEventListener("click", (e) => {
          this.generateContent("linkedin_message", e.target);
        });

      // Application Checklist - Track Progress
      this.attachChecklistListeners();

      // Copy buttons for questions to ask
      this.attachCopyListeners();
    }

    // Checklist progress tracking
    attachChecklistListeners() {
      const checklist = this.container.querySelector(".audit-checklist");
      if (!checklist) return;

      const checkboxes = checklist.querySelectorAll('input[type="checkbox"]');
      const progressBar = this.container.querySelector(
        ".audit-checklist__progress-fill"
      );
      const progressText = this.container.querySelector(
        ".audit-checklist__progress-text"
      );
      const totalItems = checkboxes.length;

      if (!totalItems) return;

      const updateProgress = () => {
        const checked = checklist.querySelectorAll(
          'input[type="checkbox"]:checked'
        ).length;
        const percentage = Math.round((checked / totalItems) * 100);

        if (progressBar) {
          progressBar.style.width = `${percentage}%`;
        }
        if (progressText) {
          progressText.textContent = `${checked} of ${totalItems} completed`;
        }

        // Save to localStorage
        const checklistState = {};
        checkboxes.forEach((cb, i) => {
          checklistState[i] = cb.checked;
        });
        localStorage.setItem(
          `audit_checklist_${this.jobId}`,
          JSON.stringify(checklistState)
        );
      };

      // Load saved state
      try {
        const savedState = JSON.parse(
          localStorage.getItem(`audit_checklist_${this.jobId}`)
        );
        if (savedState) {
          checkboxes.forEach((cb, i) => {
            if (savedState[i]) {
              cb.checked = true;
            }
          });
          updateProgress();
        }
      } catch (e) {
        // Ignore parse errors
      }

      // Listen for changes
      checkboxes.forEach((cb) => {
        cb.addEventListener("change", updateProgress);
      });
    }

    // Copy to clipboard functionality
    attachCopyListeners() {
      const copyButtons = this.container.querySelectorAll("[data-copy]");

      copyButtons.forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          const text = btn.dataset.copy;

          try {
            await navigator.clipboard.writeText(text);

            // Visual feedback
            const originalHTML = btn.innerHTML;
            btn.innerHTML = `${Icons.check}`;
            btn.style.color = "var(--audit-success)";

            setTimeout(() => {
              btn.innerHTML = originalHTML;
              btn.style.color = "";
            }, 2000);
          } catch (err) {
            console.error("Failed to copy:", err);
          }
        });
      });
    }

    async generateContent(contentType, buttonEl) {
      const originalText = buttonEl.innerHTML;
      buttonEl.innerHTML = `<span class="audit-btn__spinner"></span> Generating...`;
      buttonEl.disabled = true;

      try {
        const formData = new FormData();
        formData.append("action", "sffc_generate_content");
        formData.append("job_id", this.jobId);
        formData.append("content_type", contentType);
        formData.append(
          "user_context",
          JSON.stringify({
            responses: this.responses,
            health_score: this.currentReport?.health_score,
          })
        );

        const response = await fetch(
          sffc_ajax?.ajaxurl || "/wp-admin/admin-ajax.php",
          {
            method: "POST",
            body: formData,
          }
        );

        const data = await response.json();

        if (data.success) {
          this.showGeneratedContent(contentType, data.data);
        } else {
          throw new Error(data.data?.message || "Generation failed");
        }
      } catch (error) {
        console.error("Content generation error:", error);
        alert("Unable to generate content. Please try again.");
      } finally {
        buttonEl.innerHTML = originalText;
        buttonEl.disabled = false;
      }
    }

    showGeneratedContent(contentType, content) {
      const titles = {
        cover_letter: "Your Tailored Cover Letter",
        cv_template: "Your Optimized CV Template",
        email_template: "Networking Email Template",
        linkedin_message: "LinkedIn Connection Message",
      };

      // Create modal for generated content
      const modal = document.createElement("div");
      modal.className = "audit-content-modal";
      modal.innerHTML = `
                <div class="audit-content-modal__backdrop"></div>
                <div class="audit-content-modal__container">
                    <div class="audit-content-modal__header">
                        <h3>${titles[contentType] || "Generated Content"}</h3>
                        <button class="audit-content-modal__close">${
                          Icons.close
                        }</button>
                    </div>
                    <div class="audit-content-modal__body">
                        ${
                          contentType === "cv_template"
                            ? this.renderCVTemplateModal(content)
                            : `
                            <pre class="audit-content-modal__text">${this.escapeHtml(
                              content.content || ""
                            )}</pre>
                            ${
                              content.is_template
                                ? `<p class="audit-content-modal__note">${this.escapeHtml(
                                    content.note
                                  )}</p>`
                                : ""
                            }
                            ${
                              content.tips
                                ? `
                                <div class="audit-content-modal__tips">
                                    <h4>Tips:</h4>
                                    <ul>${content.tips
                                      .map(
                                        (t) => `<li>${this.escapeHtml(t)}</li>`
                                      )
                                      .join("")}</ul>
                                </div>
                            `
                                : ""
                            }
                        `
                        }
                    </div>
                    <div class="audit-content-modal__footer">
                        <button class="audit-btn audit-btn--secondary" data-action="copy">
                            ${Icons.copy}
                            Copy to Clipboard
                        </button>
                        ${
                          contentType === "cv_template"
                            ? `
                            <button class="audit-btn audit-btn--primary" data-action="download-pdf">
                                ${Icons.download}
                                Download PDF
                            </button>
                        `
                            : ""
                        }
                    </div>
                </div>
            `;

      document.body.appendChild(modal);
      document.body.style.overflow = "hidden";

      requestAnimationFrame(() =>
        modal.classList.add("audit-content-modal--visible")
      );

      // Close handlers
      const closeModal = () => {
        modal.classList.remove("audit-content-modal--visible");
        document.body.style.overflow = "";
        setTimeout(() => modal.remove(), 300);
      };

      modal
        .querySelector(".audit-content-modal__close")
        .addEventListener("click", closeModal);
      modal
        .querySelector(".audit-content-modal__backdrop")
        .addEventListener("click", closeModal);

      // Copy handler
      modal
        .querySelector('[data-action="copy"]')
        ?.addEventListener("click", () => {
          const text =
            content.content || JSON.stringify(content.template, null, 2);
          navigator.clipboard.writeText(text).then(() => {
            const btn = modal.querySelector('[data-action="copy"]');
            btn.innerHTML = `${Icons.check} Copied!`;
            setTimeout(
              () => (btn.innerHTML = `${Icons.copy} Copy to Clipboard`),
              2000
            );
          });
        });
    }

    renderCVTemplateModal(content) {
      const template = content.template;
      return `
                <div class="audit-cv-modal">
                    <div class="audit-cv-modal__preview">
                        ${template.sections
                          .map(
                            (section) => `
                            <div class="audit-cv-modal__section">
                                <h4>${section.title || section.name}</h4>
                                ${
                                  section.content
                                    ? `<p>${this.escapeHtml(
                                        typeof section.content === "string"
                                          ? section.content
                                          : JSON.stringify(section.content)
                                      )}</p>`
                                    : ""
                                }
                                ${
                                  section.bullets
                                    ? `<ul>${section.bullets
                                        .map(
                                          (b) =>
                                            `<li>${this.escapeHtml(b)}</li>`
                                        )
                                        .join("")}</ul>`
                                    : ""
                                }
                                ${
                                  section.items
                                    ? `<ul>${section.items
                                        .map(
                                          (i) =>
                                            `<li>${this.escapeHtml(i)}</li>`
                                        )
                                        .join("")}</ul>`
                                    : ""
                                }
                            </div>
                        `
                          )
                          .join("")}
                    </div>
                    <div class="audit-cv-modal__notes">
                        <h4>Formatting Guidelines:</h4>
                        <ul>${template.styling_notes
                          .map((n) => `<li>${this.escapeHtml(n)}</li>`)
                          .join("")}</ul>
                    </div>
                </div>
            `;
    }

    showDirectApplyWarning(report) {
      const criticalCount = report.issues.counts.critical;
      const warningCount = report.issues.counts.warning;
      const totalIssues = criticalCount + warningCount;
      const score = report.health_score;

      const modal = document.createElement("div");
      modal.className = "audit-warning-modal";
      modal.innerHTML = `
                <div class="audit-warning-modal__backdrop"></div>
                <div class="audit-warning-modal__container">
                    <div class="audit-warning-modal__header">
                        <div class="audit-warning-modal__icon ${
                          criticalCount > 0
                            ? "audit-warning-modal__icon--danger"
                            : "audit-warning-modal__icon--warning"
                        }">
                            ${
                              criticalCount > 0
                                ? Icons.alertCircle
                                : Icons.warning
                            }
                        </div>
                        <h3>${
                          criticalCount > 0
                            ? "Critical Issues Detected"
                            : "Application Not Optimized"
                        }</h3>
                    </div>
                    <div class="audit-warning-modal__body">
                        <div class="audit-warning-modal__stats">
                            <div class="audit-warning-modal__stat">
                                <span class="audit-warning-modal__stat-value ${
                                  score < 50
                                    ? "audit-warning-modal__stat-value--danger"
                                    : ""
                                }">${score}%</span>
                                <span class="audit-warning-modal__stat-label">Match Score</span>
                            </div>
                            <div class="audit-warning-modal__stat">
                                <span class="audit-warning-modal__stat-value audit-warning-modal__stat-value--danger">${totalIssues}</span>
                                <span class="audit-warning-modal__stat-label">Unresolved Issues</span>
                            </div>
                            <div class="audit-warning-modal__stat">
                                <span class="audit-warning-modal__stat-value">${Math.max(
                                  15,
                                  100 - score - 20
                                )}%</span>
                                <span class="audit-warning-modal__stat-label">Rejection Risk</span>
                            </div>
                        </div>

                        ${
                          criticalCount > 0
                            ? `
                        <div class="audit-warning-modal__issues">
                            <p class="audit-warning-modal__issues-title">Critical gaps that may cause rejection:</p>
                            <ul>
                                ${report.issues.critical
                                  .slice(0, 3)
                                  .map(
                                    (issue) => `
                                    <li>${this.escapeHtml(
                                      issue.skill_name || issue.category
                                    )}</li>
                                `
                                  )
                                  .join("")}
                            </ul>
                        </div>
                        `
                            : ""
                        }

                        <div class="audit-warning-modal__comparison">
                            <div class="audit-warning-modal__comparison-item">
                                <span class="audit-warning-modal__comparison-label">Without optimization</span>
                                <div class="audit-warning-modal__comparison-bar">
                                    <div class="audit-warning-modal__comparison-fill audit-warning-modal__comparison-fill--low" style="width: ${Math.min(
                                      score,
                                      40
                                    )}%"></div>
                                </div>
                                <span class="audit-warning-modal__comparison-value">${Math.min(
                                  score,
                                  40
                                )}% success rate</span>
                            </div>
                            <div class="audit-warning-modal__comparison-item audit-warning-modal__comparison-item--highlight">
                                <span class="audit-warning-modal__comparison-label">With Smart message</span>
                                <div class="audit-warning-modal__comparison-bar">
                                    <div class="audit-warning-modal__comparison-fill audit-warning-modal__comparison-fill--high" style="width: ${Math.min(
                                      score + 35,
                                      85
                                    )}%"></div>
                                </div>
                                <span class="audit-warning-modal__comparison-value">${Math.min(
                                  score + 35,
                                  85
                                )}% success rate</span>
                            </div>
                        </div>
                    </div>
                    <div class="audit-warning-modal__footer">
                        <button class="audit-btn audit-btn--primary" data-action="smart-apply">
                            ${Icons.zap}
                            Optimize with Smart message
                        </button>
                        <button class="audit-btn audit-btn--ghost" data-action="continue-anyway">
                            Continue without optimization
                        </button>
                    </div>
                </div>
            `;

      document.body.appendChild(modal);
      document.body.style.overflow = "hidden";
      requestAnimationFrame(() =>
        modal.classList.add("audit-warning-modal--visible")
      );

      const closeModal = () => {
        modal.classList.remove("audit-warning-modal--visible");
        document.body.style.overflow = "";
        setTimeout(() => modal.remove(), 300);
      };

      modal
        .querySelector(".audit-warning-modal__backdrop")
        .addEventListener("click", closeModal);

      modal
        .querySelector('[data-action="smart-apply"]')
        .addEventListener("click", () => {
          closeModal();
          this.initiateSmartApply(report);
        });

      modal
        .querySelector('[data-action="continue-anyway"]')
        .addEventListener("click", () => {
          closeModal();
          window.open(report.job_data.application_url || "#", "_blank");
        });
    }

    initiateSmartApply(report) {
      sessionStorage.setItem("sffc_audit_report", JSON.stringify(report));
      sessionStorage.setItem("sffc_audit_job_id", this.jobId);

      const event = new CustomEvent("sffc:smart-apply-requested", {
        detail: { jobId: this.jobId, report, responses: this.responses },
      });
      document.dispatchEvent(event);

      setTimeout(() => {
        if (typeof SennaSmartApply !== "undefined") {
          SennaSmartApply.open(this.jobId, report);
        } else if (typeof SennaChat !== "undefined") {
          SennaChat.send(
            `Help me apply to this ${report.job_data.job_title} role. My audit score is ${report.health_score}%.`
          );
        } else {
          window.location.href =
            "/smart-apply/?job_id=" + this.jobId + "&from_audit=1";
        }
      }, 100);
    }
  }

  // Modal helper
  function createModal(jobId) {
    const overlay = document.createElement("div");
    overlay.className = "audit-modal";
    overlay.innerHTML = `
            <div class="audit-modal__backdrop"></div>
            <div class="audit-modal__container">
                <button class="audit-modal__close" aria-label="Close">
                    ${Icons.close}
                </button>
                <div class="sffc-audit-wrapper" data-job-id="${jobId}"></div>
            </div>
        `;
    document.body.appendChild(overlay);
    document.body.style.overflow = "hidden";

    requestAnimationFrame(() => {
      overlay.classList.add("audit-modal--visible");
    });

    const close = () => {
      overlay.classList.remove("audit-modal--visible");
      document.body.style.overflow = "";
      setTimeout(() => overlay.remove(), 300);
    };

    overlay
      .querySelector(".audit-modal__close")
      .addEventListener("click", close);
    overlay
      .querySelector(".audit-modal__backdrop")
      .addEventListener("click", close);
    document.addEventListener("keydown", function escHandler(e) {
      if (e.key === "Escape") {
        close();
        document.removeEventListener("keydown", escHandler);
      }
    });

    return overlay.querySelector(".sffc-audit-wrapper");
  }

  // Public API
  window.SennaApplicationAudit = {
    init: function (jobId, containerSelector) {
      const container =
        typeof containerSelector === "string"
          ? document.querySelector(containerSelector)
          : containerSelector;
      if (!container) {
        console.error("Audit container not found:", containerSelector);
        return null;
      }
      return new ApplicationAuditV3(jobId, container);
    },

    start: function (jobId, containerSelector) {
      return this.init(jobId, containerSelector);
    },

    openModal: function (jobId) {
      const container = createModal(jobId);
      return new ApplicationAuditV3(jobId, container);
    },

    interceptApply: function (jobId) {
      return this.openModal(jobId);
    },
  };

  // Auto-init
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-audit-job-id]").forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        window.SennaApplicationAudit.openModal(this.dataset.auditJobId);
      });
    });

    document
      .querySelectorAll(".sffc-audit-wrapper[data-job-id]")
      .forEach((container) => {
        const jobId = container.dataset.jobId;
        if (jobId && !container.dataset.initialized) {
          container.dataset.initialized = "true";
          new ApplicationAuditV3(jobId, container);
        }
      });
  });
})();
