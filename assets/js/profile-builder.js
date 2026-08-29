/**
 * Premium Profile Builder JavaScript
 * MENA Careers-guided sequenced interface with typing effects
 */

// Global functions - Define on window object immediately
(function () {
  // Create the profileBuilder object that other modules expect
  window.profileBuilder = {
    profileData: {},
    open: function () {
      window.openProfileBuilder();
    },
    close: function () {
      window.closeProfileBuilder();
    },
    getProfileData: function () {
      return this.profileData;
    },
    setProfileData: function (data) {
      this.profileData = data;
    },
  };

  window.openProfileBuilder = function () {
    // Check if user is logged in first
    if (!window.sffc_profile?.is_logged_in && !window.sffc_ajax?.user_id) {
      showPremiumAuthForm();
      return;
    }

    // Check if profile is already complete
    const profileData = getCompleteProfileData();
    if (isProfileComplete(profileData)) {
      // Profile is complete, go directly to application
      console.log("Profile is complete, redirecting to application");
      startApplicationProcess(profileData);
      return;
    }

    jQuery("#profile-builder")
      .addClass("active")
      .fadeIn(300)
      .css("display", "flex");
    // Start the sequence
    if (window.profileSequence) {
      window.profileSequence.start();
    }
  };

  window.closeProfileBuilder = function () {
    jQuery("#profile-builder").removeClass("active").fadeOut(300);

    // Trigger event for Apply Mode to refresh
    jQuery(document).trigger("profileBuilder:closed");
  };

  // Check if profile is complete
  window.isProfileComplete = function (profile) {
    if (!profile) return false;

    // Required fields for a complete profile
    const requiredFields = [
      "full_name",
      "email",
      "skills",
      "experience",
      "education",
    ];

    // Check if all required fields are present and not empty
    for (const field of requiredFields) {
      if (
        !profile[field] ||
        (Array.isArray(profile[field]) && profile[field].length === 0)
      ) {
        return false;
      }
    }

    return true;
  };

  // Get complete profile data from all sources
  window.getCompleteProfileData = function () {
    // Try localStorage first
    let profileData = {};
    try {
      const storedProfile = localStorage.getItem("sffc_user_profile");
      if (storedProfile) {
        profileData = JSON.parse(storedProfile);
      }
    } catch (e) {
      console.error("Error loading profile from localStorage:", e);
    }

    // Merge with any data from profileBuilder
    if (window.profileBuilder?.profileData) {
      profileData = { ...profileData, ...window.profileBuilder.profileData };
    }

    // Merge with any data from sequential collector
    if (window.profileData) {
      profileData = { ...profileData, ...window.profileData };
    }

    return profileData;
  };

  // Start application process with completed profile
  window.startApplicationProcess = function (profileData) {
    // Save profile data to ensure it's available
    if (profileData) {
      localStorage.setItem("sffc_user_profile", JSON.stringify(profileData));
      window.profileBuilder.profileData = profileData;
    }

    // Check if sequential collector exists and start it
    if (window.sequentialCollector) {
      // Skip profile building, go directly to application
      window.sequentialCollector.skipToApplication(profileData);
    } else if (jQuery(".btn-tailor-cv").length) {
      // CV Tailoring available but don't auto-trigger
      console.log("CV Tailoring buttons available");
    } else {
      // Fallback: show application interface
      jQuery(document).trigger("startApplication", [profileData]);
    }
  };

  // Proceed with application after profile completion check
  window.proceedWithApplication = function () {
    const profileData = getCompleteProfileData();

    // Close any open dialogs
    $("#sequential-collector").fadeOut(300, function () {
      $(this).remove();
    });

    // Trigger application start
    if (window.ApplicationSubmission) {
      const submission = new window.ApplicationSubmission();
      submission.initWithProfile(profileData);
    } else {
      // Trigger event for other components
      $(document).trigger("application:start", [profileData]);

      // Try to find and click apply button
      if ($(".btn-tailor-cv").length) {
        // CV Tailoring available
        console.log("CV Tailoring ready");
      }
    }
  };

  // Premium Authentication Form Function
  window.showPremiumAuthForm = function () {
    // Remove any existing auth forms
    jQuery("#premium-auth-form").remove();

    const loginUrl =
      window.sffc_login_url ||
      window.sffc_ajax?.login_url ||
      "https://joinsenna.com/login-auth/";
    const registrationUrl =
      window.sffc_ajax?.registration_url || "https://joinsenna.com/memberships/";

    const authFormHtml = `
            <div id="premium-auth-form" class="premium-auth-overlay">
                <div class="premium-auth-panel">
                    <button class="auth-close" aria-label="Close">×</button>
                    
                    <div class="auth-content">
                        <div class="auth-avatar">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        
                        <div class="auth-message">
                            <h3 class="auth-title">Create Your Profile</h3>
                            <p>Sign in to save your profile and unlock personalized job recommendations tailored just for you.</p>
                        </div>
                        
                        <div class="auth-actions">
                            <a href="${loginUrl}?redirect_to=${encodeURIComponent(
      window.location.href
    )}" class="auth-btn primary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <polyline points="10 17 15 12 10 7"></polyline>
                                    <line x1="15" y1="12" x2="3" y2="12"></line>
                                </svg>
                                Sign In to Continue
                            </a>
                            <a href="${registrationUrl}?redirect_to=${encodeURIComponent(
      window.location.href
    )}" class="auth-btn secondary">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Create New Account
                            </a>
                        </div>
                        
                        <div class="auth-benefits">
                            <div class="benefit-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <span>Save your profile permanently</span>
                            </div>
                            <div class="benefit-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                                <span>Get personalized job matches</span>
                            </div>
                            <div class="benefit-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                <span>Track your applications</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="auth-footer">
                        <div class="auth-security">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Your data is secure and private
                        </div>
                    </div>
                </div>
            </div>
        `;

    jQuery("body").append(authFormHtml);

    // Add animation
    setTimeout(() => {
      jQuery("#premium-auth-form").addClass("active");
    }, 10);

    // Bind close events
    jQuery(".auth-close, #premium-auth-form").on("click", function (e) {
      if (e.target === this) {
        jQuery("#premium-auth-form").removeClass("active");
        setTimeout(() => {
          jQuery("#premium-auth-form").remove();
        }, 300);
      }
    });

    // Prevent closing when clicking inside panel
    jQuery(".premium-auth-panel").on("click", function (e) {
      e.stopPropagation();
    });
  };
})();

jQuery(document).ready(function ($) {
  console.log("SFFC Profile Builder: Premium interface initializing...");

  // State management
  let currentQuestionIndex = 0;
  let profileData = {};
  let isTyping = false;
  let typingTimer = null;
  let selectedCount = 0;

  // Check if profile builder exists
  if ($("#profile-builder").length === 0) {
    console.log("SFFC Profile Builder: Element not found");
    return;
  }

  // Profile Sequence Controller
  class ProfileSequence {
    constructor() {
      this.questions = window.profileQuestions || [];
      this.currentIndex = 0;
      this.answers = {};
      this.isAnimating = false;

      // Load existing profile data
      const existingData = $("#profile-data").text();
      if (existingData) {
        try {
          const parsed = JSON.parse(existingData);
          if (parsed && typeof parsed === "object") {
            this.answers = parsed;
            profileData = parsed;
          }
        } catch (e) {
          console.log("No existing profile data");
        }
      }
    }

    start() {
      this.currentIndex = 0;
      this.showQuestion(0);
    }

    showQuestion(index) {
      if (this.isAnimating || index >= this.questions.length) return;

      this.isAnimating = true;
      this.currentIndex = index;
      const question = this.questions[index];

      // Update progress
      this.updateProgress(index + 1);

      // Clear previous content with fade
      $("#answer-area").fadeOut(200, () => {
        $("#answer-area").empty();

        // Process question text with replacements
        let questionText = question.question;
        if (this.answers.full_name && questionText.includes("{name}")) {
          const firstName = this.answers.full_name.split(" ")[0];
          questionText = questionText.replace("{name}", firstName);
        }

        // Type the question with Playfair font
        this.typeQuestion(questionText, () => {
          // Show answer options after typing
          this.showAnswerOptions(question);
          this.isAnimating = false;
        });
      });

      // Update navigation
      if (index > 0) {
        $("#nav-back").fadeIn();
      } else {
        $("#nav-back").fadeOut();
      }

      // Update skip button for last question
      if (index === this.questions.length - 1) {
        $("#nav-skip").hide();
      } else {
        $("#nav-skip").show();
      }
    }

    typeQuestion(text, callback) {
      const $questionText = $("#question-text");
      $questionText.empty();

      // Add typing indicator
      $questionText.html('<span class="typing-cursor">|</span>');

      let charIndex = 0;
      const typeChar = () => {
        if (charIndex < text.length) {
          const currentText = text.substring(0, charIndex + 1);
          $questionText.html(
            currentText + '<span class="typing-cursor">|</span>'
          );
          charIndex++;

          // Variable typing speed for natural effect
          const delay =
            text[charIndex - 1] === "."
              ? 300
              : text[charIndex - 1] === ","
              ? 150
              : Math.random() * 30 + 20;

          setTimeout(typeChar, delay);
        } else {
          // Remove cursor after typing
          setTimeout(() => {
            $questionText.find(".typing-cursor").fadeOut(400);
            if (callback) callback();
          }, 500);
        }
      };

      // Start typing after brief delay
      setTimeout(typeChar, 300);
    }

    showAnswerOptions(question) {
      const $answerArea = $("#answer-area");
      $answerArea.empty();

      switch (question.type) {
        case "text":
          this.createTextInput(question);
          break;

        case "choice":
          this.createChoiceButtons(question);
          break;

        case "multi-choice":
          this.createMultiChoice(question);
          break;

        case "location-select":
          this.createLocationSelect(question);
          break;

        case "salary-range":
          this.createSalaryRange(question);
          break;

        case "skill-input":
          this.createSkillInput(question);
          break;

        case "threshold-slider":
          this.createThresholdSlider(question);
          break;

        case "completion":
          this.showCompletion(question);
          break;
      }

      // Fade in answer area
      $answerArea.fadeIn(400);
    }

    createTextInput(question) {
      const html = `
                <div class="sffc-pb-text-input-wrapper">
                    <input type="text" 
                           class="sffc-pb-text-input" 
                           id="answer-input"
                           placeholder="${
                             question.placeholder || "Type your answer..."
                           }"
                           autocomplete="off">
                    <button class="sffc-pb-submit-btn" id="submit-text">
                        <span>Continue</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
                        </svg>
                    </button>
                </div>
            `;

      $("#answer-area").html(html);

      // Focus input after animation
      setTimeout(() => {
        $("#answer-input").focus();
      }, 100);

      // Handle submit
      const submitAnswer = () => {
        const value = $("#answer-input").val().trim();
        if (value) {
          this.saveAnswer(question.field, value);
          this.nextQuestion();
        }
      };

      $("#submit-text").on("click", submitAnswer);
      $("#answer-input").on("keypress", (e) => {
        if (e.which === 13) submitAnswer();
      });
    }

    createChoiceButtons(question) {
      const html = `
                <div class="sffc-pb-choice-grid">
                    ${question.options
                      .map(
                        (option) => `
                        <button class="sffc-pb-choice-btn" data-value="${
                          option.value
                        }">
                            ${
                              option.icon
                                ? `<span class="choice-icon">${option.icon}</span>`
                                : ""
                            }
                            <span class="choice-label">${option.label}</span>
                            ${
                              option.description
                                ? `<span class="choice-description">${option.description}</span>`
                                : ""
                            }
                        </button>
                    `
                      )
                      .join("")}
                </div>
            `;

      $("#answer-area").html(html);

      // Animate buttons in sequence
      $(".sffc-pb-choice-btn").each(function (index) {
        $(this)
          .css("opacity", 0)
          .delay(index * 50)
          .animate({ opacity: 1 }, 300);
      });

      // Handle selection
      $(".sffc-pb-choice-btn").on("click", (e) => {
        const $btn = $(e.currentTarget);
        const value = $btn.data("value");

        // Animate selection
        $btn.addClass("selected");
        $(".sffc-pb-choice-btn").not($btn).addClass("not-selected");

        setTimeout(() => {
          this.saveAnswer(question.field, value);
          this.nextQuestion();
        }, 400);
      });
    }

    createMultiChoice(question) {
      const limit = question.limit || 3;
      let selected = [];

      const html = `
                <div class="sffc-pb-multi-choice-wrapper">
                    <div class="sffc-pb-selection-hint">
                        Select up to ${limit} options
                        <span class="selection-count">0 / ${limit}</span>
                    </div>
                    <div class="sffc-pb-multi-choice-grid">
                        ${question.options
                          .map(
                            (option) => `
                            <button class="sffc-pb-multi-choice-btn" data-value="${
                              option.value
                            }">
                                ${
                                  option.icon
                                    ? `<span class="choice-icon">${option.icon}</span>`
                                    : ""
                                }
                                <span class="choice-label">${
                                  option.label
                                }</span>
                                <span class="choice-check"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                            </button>
                        `
                          )
                          .join("")}
                    </div>
                    <button class="sffc-pb-continue-btn" id="continue-multi" disabled>
                        <span>Continue</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
                        </svg>
                    </button>
                </div>
            `;

      $("#answer-area").html(html);

      // Animate options
      $(".sffc-pb-multi-choice-btn").each(function (index) {
        $(this)
          .css("opacity", 0)
          .delay(index * 30)
          .animate({ opacity: 1 }, 300);
      });

      // Handle multi-selection
      $(".sffc-pb-multi-choice-btn").on("click", function () {
        const $btn = $(this);
        const value = $btn.data("value");

        if ($btn.hasClass("selected")) {
          $btn.removeClass("selected");
          selected = selected.filter((v) => v !== value);
        } else if (selected.length < limit) {
          $btn.addClass("selected");
          selected.push(value);
        }

        // Update counter
        $(".selection-count").text(`${selected.length} / ${limit}`);

        // Enable/disable continue button
        $("#continue-multi").prop("disabled", selected.length === 0);
      });

      // Handle continue
      $("#continue-multi").on("click", () => {
        if (selected.length > 0) {
          this.saveAnswer(question.field, selected);
          this.nextQuestion();
        }
      });
    }

    createLocationSelect(question) {
      const limit = question.limit || 3;
      let selected = [];
      const cities = question.options;

      const html = `
                <div class="sffc-pb-location-wrapper">
                    <div class="sffc-pb-selection-hint">
                        Choose up to ${limit} preferred locations
                        <span class="selection-count">0 / ${limit}</span>
                    </div>
                    <div class="sffc-pb-location-grid">
                        ${Object.entries(cities)
                          .map(
                            ([key, city]) => `
                            <button class="sffc-pb-location-btn" data-value="${key}">
                                <span class="location-name">${city}</span>
                                <span class="location-selected"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
                            </button>
                        `
                          )
                          .join("")}
                    </div>
                    <button class="sffc-pb-continue-btn" id="continue-locations" disabled>
                        <span>Continue with selected cities</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
                        </svg>
                    </button>
                </div>
            `;

      $("#answer-area").html(html);

      // Animate cities
      $(".sffc-pb-location-btn").each(function (index) {
        $(this)
          .css("opacity", 0)
          .delay(index * 20)
          .animate({ opacity: 1 }, 300);
      });

      // Handle selection
      $(".sffc-pb-location-btn").on("click", function () {
        const $btn = $(this);
        const value = $btn.data("value");

        if ($btn.hasClass("selected")) {
          $btn.removeClass("selected");
          selected = selected.filter((v) => v !== value);
        } else if (selected.length < limit) {
          $btn.addClass("selected");
          selected.push(value);
        }

        $(".selection-count").text(`${selected.length} / ${limit}`);
        $("#continue-locations").prop("disabled", selected.length === 0);
      });

      $("#continue-locations").on("click", () => {
        if (selected.length > 0) {
          this.saveAnswer(question.field, selected);
          this.nextQuestion();
        }
      });
    }

    createSalaryRange(question) {
      const html = `
                <div class="sffc-pb-salary-wrapper">
                    <div class="sffc-pb-salary-grid">
                        ${question.ranges
                          .map(
                            (range) => `
                            <button class="sffc-pb-salary-btn" 
                                    data-min="${range.min}" 
                                    data-max="${range.max}">
                                <span class="salary-label">${range.label}</span>
                                <span class="salary-badge">Select</span>
                            </button>
                        `
                          )
                          .join("")}
                    </div>
                </div>
            `;

      $("#answer-area").html(html);

      // Animate salary options
      $(".sffc-pb-salary-btn").each(function (index) {
        $(this)
          .css("opacity", 0)
          .delay(index * 40)
          .animate({ opacity: 1 }, 300);
      });

      // Handle selection
      $(".sffc-pb-salary-btn").on(
        "click",
        function () {
          const $btn = $(this);
          const min = $btn.data("min");
          const max = $btn.data("max");

          $btn.addClass("selected");
          $(".sffc-pb-salary-btn").not($btn).addClass("not-selected");

          setTimeout(() => {
            this.saveAnswer("salary_target_min", min);
            this.saveAnswer("salary_target_max", max);
            this.saveAnswer(question.field, { min, max });
            this.nextQuestion();
          }, 400);
        }.bind(this)
      );
    }

    createSkillInput(question) {
      const maxSkills = question.max || 5;
      const minSkills = question.min || 3;
      let skills = [];

      const html = `
                <div class="sffc-pb-skill-wrapper">
                    <div class="sffc-pb-skill-input-container">
                        <input type="text" 
                               class="sffc-pb-skill-input" 
                               id="skill-input"
                               placeholder="Type a skill and press Enter..."
                               autocomplete="off">
                        <div class="skill-count">${
                          skills.length
                        } / ${maxSkills}</div>
                    </div>
                    <div class="sffc-pb-skill-tags" id="skill-tags"></div>
                    <div class="sffc-pb-skill-suggestions">
                        ${question.suggestions
                          .map(
                            (skill) => `
                            <button class="skill-suggestion" data-skill="${skill}">${skill}</button>
                        `
                          )
                          .join("")}
                    </div>
                    <button class="sffc-pb-continue-btn" id="continue-skills" disabled>
                        <span>Continue with ${minSkills}+ skills</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
                        </svg>
                    </button>
                </div>
            `;

      $("#answer-area").html(html);

      const updateSkillDisplay = () => {
        $("#skill-tags").html(
          skills
            .map(
              (skill) => `
                    <span class="skill-tag">
                        ${skill}
                        <button class="remove-skill" data-skill="${skill}">×</button>
                    </span>
                `
            )
            .join("")
        );

        $(".skill-count").text(`${skills.length} / ${maxSkills}`);
        $("#continue-skills").prop("disabled", skills.length < minSkills);

        // Bind remove handlers
        $(".remove-skill").on("click", function () {
          const skillToRemove = $(this).data("skill");
          skills = skills.filter((s) => s !== skillToRemove);
          updateSkillDisplay();
        });
      };

      // Add skill function
      const addSkill = (skill) => {
        if (skill && !skills.includes(skill) && skills.length < maxSkills) {
          skills.push(skill);
          updateSkillDisplay();
          $("#skill-input").val("").focus();
        }
      };

      // Handle input
      $("#skill-input").on("keypress", (e) => {
        if (e.which === 13) {
          e.preventDefault();
          addSkill($("#skill-input").val().trim());
        }
      });

      // Handle suggestions
      $(".skill-suggestion").on("click", function () {
        addSkill($(this).data("skill"));
        $(this).fadeOut(200);
      });

      // Handle continue
      $("#continue-skills").on("click", () => {
        if (skills.length >= minSkills) {
          this.saveAnswer(question.field, skills);
          this.nextQuestion();
        }
      });
    }

    createThresholdSlider(question) {
      const min = question.min || 40;
      const max = question.max || 90;
      const defaultVal = question.default || 60;
      const step = question.step || 5;
      const labels = question.labels || {};

      const html = `
        <div class="sffc-pb-threshold-wrapper">
          <div class="sffc-pb-threshold-display">
            <span class="threshold-value" id="threshold-value">${defaultVal}%</span>
            <span class="threshold-label" id="threshold-label">${labels[defaultVal] || ''}</span>
          </div>
          <div class="sffc-pb-threshold-slider-container">
            <input type="range"
                   class="sffc-pb-threshold-slider"
                   id="threshold-slider"
                   min="${min}"
                   max="${max}"
                   value="${defaultVal}"
                   step="${step}">
            <div class="sffc-pb-threshold-ticks">
              <span class="tick-label">${min}%</span>
              <span class="tick-label">${Math.round((min + max) / 2)}%</span>
              <span class="tick-label">${max}%</span>
            </div>
          </div>
          <div class="sffc-pb-threshold-hints">
            <div class="hint-item">
              <span class="hint-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18 20V10M12 20V4M6 20v-6"/>
                </svg>
              </span>
              <span class="hint-text">Lower threshold = more job matches shown</span>
            </div>
            <div class="hint-item">
              <span class="hint-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <circle cx="12" cy="12" r="6"/>
                  <circle cx="12" cy="12" r="2"/>
                </svg>
              </span>
              <span class="hint-text">Higher threshold = only best matches</span>
            </div>
          </div>
          <button class="sffc-pb-continue-btn" id="continue-threshold">
            <span>Set my threshold</span>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
            </svg>
          </button>
        </div>
      `;

      $("#answer-area").html(html);

      const $slider = $("#threshold-slider");
      const $value = $("#threshold-value");
      const $label = $("#threshold-label");

      // Update display on slider change
      $slider.on("input", function() {
        const val = parseInt($(this).val());
        $value.text(val + "%");

        // Update label based on value
        let labelText = "";
        if (val <= 45) {
          labelText = labels[40] || "Show more jobs";
        } else if (val <= 65) {
          labelText = labels[60] || "Balanced";
        } else {
          labelText = labels[90] || "Only top matches";
        }
        $label.text(labelText);

        // Update slider fill color based on value
        const percentage = ((val - min) / (max - min)) * 100;
        $(this).css("background", `linear-gradient(to right, #3B82F6 0%, #3B82F6 ${percentage}%, #E5E7EB ${percentage}%, #E5E7EB 100%)`);
      });

      // Trigger initial fill
      $slider.trigger("input");

      // Handle continue
      $("#continue-threshold").on("click", () => {
        const value = parseInt($slider.val());
        this.saveAnswer(question.field, value);
        this.nextQuestion();
      });
    }

    showCompletion(question) {
      const threshold = this.answers.match_threshold || 60;
      const firstName = this.answers.full_name ? this.answers.full_name.split(' ')[0] : 'there';

      // Format salary range
      const formatSalary = (val) => {
        if (!val) return '';
        const num = parseInt(val);
        if (num >= 1000) return `£${(num / 1000).toFixed(0)}k`;
        return `£${num}`;
      };

      const salaryDisplay = this.answers.salary_target_min && this.answers.salary_target_max
        ? `${formatSalary(this.answers.salary_target_min)} - ${formatSalary(this.answers.salary_target_max)}`
        : '';

      // Format locations
      const locationsDisplay = this.answers.preferred_locations
        ? (Array.isArray(this.answers.preferred_locations)
            ? this.answers.preferred_locations.join(', ')
            : this.answers.preferred_locations)
        : '';

      // Format skills as tags
      const skillsTags = this.answers.skills
        ? this.answers.skills.map(skill => `<span class="sffc-pb-summary-tag">${skill}</span>`).join('')
        : '';

      // Format certifications as tags
      const certTags = this.answers.certifications && this.answers.certifications.length > 0 && this.answers.certifications[0] !== 'none'
        ? this.answers.certifications.map(cert => `<span class="sffc-pb-summary-tag">${cert}</span>`).join('')
        : '';

      // Format experience level
      const experienceDisplay = this.answers.years_experience
        ? `${this.answers.years_experience} years`
        : '';

      // Format seniority
      const seniorityDisplay = this.answers.target_seniority
        ? this.answers.target_seniority.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : '';

      // Format industries (field name is preferred_industries in PHP)
      const industriesTags = this.answers.preferred_industries
        ? (Array.isArray(this.answers.preferred_industries)
            ? this.answers.preferred_industries.map(ind => `<span class="sffc-pb-summary-tag">${ind.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</span>`).join('')
            : `<span class="sffc-pb-summary-tag">${this.answers.preferred_industries}</span>`)
        : '';

      // Format career priorities
      const prioritiesTags = this.answers.career_priorities
        ? (Array.isArray(this.answers.career_priorities)
            ? this.answers.career_priorities.map(p => `<span class="sffc-pb-summary-tag">${p.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</span>`).join('')
            : `<span class="sffc-pb-summary-tag">${this.answers.career_priorities}</span>`)
        : '';

      // Format education level
      const educationDisplay = this.answers.education_level
        ? this.answers.education_level.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : '';

      // Format languages
      const languagesTags = this.answers.languages_spoken
        ? (Array.isArray(this.answers.languages_spoken)
            ? this.answers.languages_spoken.map(lang => `<span class="sffc-pb-summary-tag">${lang.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</span>`).join('')
            : `<span class="sffc-pb-summary-tag">${this.answers.languages_spoken}</span>`)
        : '';

      // Format availability
      const availabilityDisplay = this.answers.availability
        ? this.answers.availability.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : '';

      // Format company size preference
      const companySizeDisplay = this.answers.preferred_company_size
        ? this.answers.preferred_company_size.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : '';

      // Format work preferences
      const workPrefDisplay = this.answers.work_preference
        ? this.answers.work_preference.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : '';

      const html = `
        <div class="sffc-pb-completion">
          <div class="completion-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <h3 class="completion-title">You're all set, ${firstName}!</h3>

          <!-- Match Threshold Card -->
          <div class="sffc-pb-threshold-display-card">
            <div class="sffc-pb-threshold-info">
              <h4>Match Threshold</h4>
              <p>Only showing jobs that match at least this percentage</p>
            </div>
            <div class="sffc-pb-threshold-badge">${threshold}%</div>
          </div>

          <!-- Profile Summary Card -->
          <div class="sffc-pb-profile-summary">
            <div class="sffc-pb-summary-header">
              <h4 class="sffc-pb-summary-title">Your Career Profile</h4>
              <button class="sffc-pb-summary-edit" id="edit-profile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                Edit Profile
              </button>
            </div>

            <div class="sffc-pb-summary-grid">
              ${this.answers.full_name ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                  Full Name
                </div>
                <div class="sffc-pb-summary-value">${this.answers.full_name}</div>
              </div>
              ` : ''}

              ${this.answers.email ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                  </svg>
                  Email
                </div>
                <div class="sffc-pb-summary-value">${this.answers.email}</div>
              </div>
              ` : ''}

              ${this.answers.current_role ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                  </svg>
                  Current Role
                </div>
                <div class="sffc-pb-summary-value">${this.answers.current_role}</div>
              </div>
              ` : ''}

              ${experienceDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                  </svg>
                  Experience
                </div>
                <div class="sffc-pb-summary-value">${experienceDisplay}</div>
              </div>
              ` : ''}

              ${seniorityDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 20V10"/>
                    <path d="M12 20V4"/>
                    <path d="M6 20v-6"/>
                  </svg>
                  Target Level
                </div>
                <div class="sffc-pb-summary-value">${seniorityDisplay}</div>
              </div>
              ` : ''}

              ${workPrefDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                  </svg>
                  Work Preference
                </div>
                <div class="sffc-pb-summary-value">${workPrefDisplay}</div>
              </div>
              ` : ''}

              ${salaryDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                  </svg>
                  Salary Target
                </div>
                <div class="sffc-pb-summary-value">${salaryDisplay}</div>
              </div>
              ` : ''}

              ${locationsDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                  </svg>
                  Preferred Locations
                </div>
                <div class="sffc-pb-summary-value">${locationsDisplay}</div>
              </div>
              ` : ''}

              ${skillsTags ? `
              <div class="sffc-pb-summary-item full-width">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                  </svg>
                  Key Skills
                </div>
                <div class="sffc-pb-summary-tags">${skillsTags}</div>
              </div>
              ` : ''}

              ${industriesTags ? `
              <div class="sffc-pb-summary-item full-width">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 20h20"/>
                    <path d="M5 20V10l7-5 7 5v10"/>
                    <path d="M9 20v-4h6v4"/>
                  </svg>
                  Target Industries
                </div>
                <div class="sffc-pb-summary-tags">${industriesTags}</div>
              </div>
              ` : ''}

              ${certTags ? `
              <div class="sffc-pb-summary-item full-width">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="8" r="7"/>
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                  </svg>
                  Certifications
                </div>
                <div class="sffc-pb-summary-tags">${certTags}</div>
              </div>
              ` : ''}

              ${prioritiesTags ? `
              <div class="sffc-pb-summary-item full-width">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                  </svg>
                  Career Priorities
                </div>
                <div class="sffc-pb-summary-tags">${prioritiesTags}</div>
              </div>
              ` : ''}

              ${languagesTags ? `
              <div class="sffc-pb-summary-item full-width">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="2" y1="12" x2="22" y2="12"/>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                  </svg>
                  Languages
                </div>
                <div class="sffc-pb-summary-tags">${languagesTags}</div>
              </div>
              ` : ''}

              ${educationDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                  </svg>
                  Education
                </div>
                <div class="sffc-pb-summary-value">${educationDisplay}</div>
              </div>
              ` : ''}

              ${companySizeDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="4" y="4" width="16" height="16" rx="2" ry="2"/>
                    <rect x="9" y="9" width="6" height="6"/>
                    <line x1="9" y1="1" x2="9" y2="4"/>
                    <line x1="15" y1="1" x2="15" y2="4"/>
                    <line x1="9" y1="20" x2="9" y2="23"/>
                    <line x1="15" y1="20" x2="15" y2="23"/>
                    <line x1="20" y1="9" x2="23" y2="9"/>
                    <line x1="20" y1="14" x2="23" y2="14"/>
                    <line x1="1" y1="9" x2="4" y2="9"/>
                    <line x1="1" y1="14" x2="4" y2="14"/>
                  </svg>
                  Company Size
                </div>
                <div class="sffc-pb-summary-value">${companySizeDisplay}</div>
              </div>
              ` : ''}

              ${availabilityDisplay ? `
              <div class="sffc-pb-summary-item">
                <div class="sffc-pb-summary-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                  </svg>
                  Availability
                </div>
                <div class="sffc-pb-summary-value">${availabilityDisplay}</div>
              </div>
              ` : ''}
            </div>
          </div>

          <button class="sffc-pb-view-matches-btn" id="view-matches">
            <span>View Your Job Matches</span>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M5 12H19M19 12L12 5M19 12L12 19"/>
            </svg>
          </button>
        </div>
      `;

      $("#answer-area").html(html);

      // Handle view matches
      $("#view-matches").on("click", () => {
        this.completeProfile();
      });

      // Handle edit profile - restart the sequence
      $("#edit-profile").on("click", () => {
        this.currentIndex = 0;
        this.showQuestion(0);
      });
    }

    saveAnswer(field, value) {
      this.answers[field] = value;
      profileData[field] = value;

      // Check if user is logged in
      if (!window.sffc_profile?.is_logged_in) {
        console.log(
          "Profile saved locally - login required for permanent save"
        );
        // Save to localStorage for persistence
        localStorage.setItem("sffc_user_profile", JSON.stringify(profileData));

        // Show login prompt on first answer
        if (Object.keys(this.answers).length === 1) {
          this.showLoginPrompt();
        }
        return;
      }

      // Save via AJAX
      $.ajax({
        url: sffc_profile.ajax_url,
        type: "POST",
        data: {
          action: "sffc_save_profile_answer",
          field: field,
          value: value,
          question_id: this.questions[this.currentIndex].id,
          nonce: sffc_profile.nonce,
        },
        success: (response) => {
          if (!response.success && response.data?.require_auth) {
            // User needs to log in
            this.showLoginPrompt();
            // Save to localStorage as backup
            localStorage.setItem(
              "sffc_user_profile",
              JSON.stringify(profileData)
            );
          } else if (response.success) {
            console.log("Profile answer saved successfully");
            // Also save to localStorage for quick access
            localStorage.setItem(
              "sffc_user_profile",
              JSON.stringify(profileData)
            );

            // Update global profile data
            if (window.profileBuilder) {
              window.profileBuilder.profileData = profileData;
            }

            // Trigger event for Apply Mode
            $(document).trigger("profileBuilder:saved", [profileData]);
          }
          console.log("Answer saved:", field);
          // Trigger profile update event
          $(document).trigger("sffc:profile:updated", [this.answers]);
        },
        error: (xhr, status, error) => {
          console.error("Failed to save profile:", error);
          // Still save to localStorage as fallback
          localStorage.setItem(
            "sffc_user_profile",
            JSON.stringify(profileData)
          );
        },
      });
    }

    nextQuestion() {
      if (this.currentIndex < this.questions.length - 1) {
        this.showQuestion(this.currentIndex + 1);
      }
    }

    previousQuestion() {
      if (this.currentIndex > 0) {
        this.showQuestion(this.currentIndex - 1);
      }
    }

    skipQuestion() {
      this.nextQuestion();
    }

    updateProgress(step) {
      const totalSteps = this.questions.length;
      const percentage = (step / totalSteps) * 100;

      $("#current-step").text(step);
      $("#progress-fill").css("width", percentage + "%");
    }

    showLoginPrompt() {
      if (this.loginPromptShown) return;
      this.loginPromptShown = true;

      const html = `
                <div class="sffc-pb-login-prompt">
                    <div class="login-prompt-content">
                        <h3>Save Your Progress</h3>
                        <p>Log in to save your profile permanently and unlock personalized job matches.</p>
                        <div class="login-prompt-buttons">
                            <a href="${
                              window.sffc_login_url ||
                              window.sffc_ajax?.login_url ||
                              "https://joinsenna.com/login-auth/"
                            }" class="btn-login">Log In Now</a>
                            <button class="btn-continue" onclick="jQuery('.sffc-pb-login-prompt').fadeOut()">Continue Without Login</button>
                        </div>
                    </div>
                </div>
            `;

      $("#profile-builder").append(html);
      $(".sffc-pb-login-prompt").fadeIn();
    }

    completeProfile() {
      // Mark profile as complete
      profileData.isComplete = true;
      profileData.completedAt = new Date().toISOString();
      // Save completion status
      $.ajax({
        url: sffc_profile.ajax_url,
        type: "POST",
        data: {
          action: "sffc_complete_profile",
          nonce: sffc_profile.nonce,
        },
        success: (response) => {
          if (response.success) {
            // Close profile builder
            closeProfileBuilder();

            // Reload opportunities if on that page
            if (typeof window.reloadOpportunitiesWithMatches === "function") {
              window.reloadOpportunitiesWithMatches();
            }

            // Or redirect if provided
            if (response.data.redirect) {
              window.location.href = response.data.redirect;
            }
          }
        },
      });
    }
  }

  // Make sure fullscreen profile builder is hidden initially (skip inline mode)
  if ($("#profile-builder").hasClass("fullscreen")) {
    $("#profile-builder").removeClass("active").hide();
  }

  // Initialize the sequence
  window.profileSequence = new ProfileSequence();

  // Auto-start sequence for inline mode
  if ($("#profile-builder").hasClass("inline") && $("#profile-builder").is(":visible")) {
    window.profileSequence.start();
  }

  // Bind navigation events
  $("#nav-back").on("click", () => {
    window.profileSequence.previousQuestion();
  });

  $("#nav-skip").on("click", () => {
    window.profileSequence.skipQuestion();
  });

  // Close button
  $(".sffc-pb-close").on("click", () => {
    closeProfileBuilder();
  });

  // ESC key to close
  $(document).on("keydown", (e) => {
    if (e.key === "Escape" && $("#profile-builder").hasClass("active")) {
      closeProfileBuilder();
    }
  });

  console.log("SFFC Profile Builder: Premium interface ready");

  // Ensure global functions are available
  if (typeof window.openProfileBuilder !== "function") {
    console.error(
      "SFFC Profile Builder: openProfileBuilder function not defined!"
    );
  } else {
    console.log(
      "SFFC Profile Builder: Global functions registered successfully"
    );
  }
});
