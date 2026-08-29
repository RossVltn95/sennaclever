/**
 * CV Upload Handler - Frontend
 * Handles CV upload, parsing, and UI interactions
 *
 * @package MENA Careers
 */

(function ($) {
  "use strict";

  class CVUploadHandler {
    constructor() {
      // Color scheme - Cream, Forest Green, Gold
      this.colors = {
        cream: "#FBF7F0",
        darkCream: "#F5EFE6",
        forestGreen: "#1A3028",
        darkForest: "#0F1F18",
        gold: "#2D6A4F",
        darkGold: "#1B4332",
        lightGold: "#E5D4A1",
      };

      this.uploadedFile = null;
      this.parsedData = null;
      this.isProcessing = false;

      this.init();
    }

    init() {
      // Don't initialize on PE Intelligence pages
      if (
        $(".sffc-intelligence-dashboard").length ||
        $(".sffc-intelligence-cards").length
      ) {
        return;
      }

      // Only create interface if wrapper exists (from shortcode)
      if (
        $("#sffc-cv-upload-wrapper").length ||
        $("#cv-upload-interface").length
      ) {
        this.createUploadInterface();
        this.bindEvents();
        this.loadPDFJS();
        this.loadMammoth();
      }
    }

    createUploadInterface() {
      // Check if interface already exists or wrapper doesn't exist
      if ($("#cv-upload-interface").length) return;
      if (!$("#sffc-cv-upload-wrapper").length) return;

      const html = `
                <div id="cv-upload-interface" class="cv-upload-container">
                    <div class="cv-upload-header">
                        <h2>Quick Profile Setup with CV</h2>
                        <p>Upload your CV/Resume to automatically fill your profile</p>
                    </div>
                    
                    <div class="cv-upload-zone" id="cv-drop-zone">
                        <div class="upload-icon">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="${this.colors.gold}" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                        </div>
                        
                        <div class="upload-text">
                            <h3>Drag & Drop your CV here</h3>
                            <p>or <button class="browse-btn">browse files</button></p>
                            <span class="file-info">Supports PDF, DOC, DOCX (Max 10MB)</span>
                        </div>
                        
                        <input type="file" id="cv-file-input" accept=".pdf,.doc,.docx" style="display: none;">
                    </div>
                    
                    <div class="cv-upload-progress" style="display: none;">
                        <div class="progress-header">
                            <span class="file-name"></span>
                            <span class="progress-status">Uploading...</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-steps">
                            <div class="step active" data-step="upload">
                                <span class="step-icon">📤</span>
                                <span class="step-label">Upload</span>
                            </div>
                            <div class="step" data-step="extract">
                                <span class="step-icon">📄</span>
                                <span class="step-label">Extract</span>
                            </div>
                            <div class="step" data-step="parse">
                                <span class="step-icon">🔍</span>
                                <span class="step-label">Parse</span>
                            </div>
                            <div class="step" data-step="complete">
                                <span class="step-icon">✅</span>
                                <span class="step-label">Complete</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cv-parse-results" style="display: none;">
                        <h3>Extracted Information</h3>
                        <div class="results-grid">
                            <!-- Results will be populated here -->
                        </div>
                        <div class="results-actions">
                            <button class="btn-edit-profile">Edit Details</button>
                            <button class="btn-confirm-profile">Looks Good, Continue</button>
                        </div>
                    </div>
                    
                    <div class="cv-error-message" style="display: none;">
                        <div class="error-icon">⚠️</div>
                        <div class="error-text"></div>
                        <button class="btn-retry">Try Again</button>
                    </div>
                </div>
            `;

      // Only add to the wrapper created by shortcode
      if ($("#sffc-cv-upload-wrapper").length) {
        $("#sffc-cv-upload-wrapper").html(html);
      } else if ($("#profile-builder").length) {
        $("#profile-builder .profile-content").prepend(html);
      }
      // Don't append to body anymore
    }

    bindEvents() {
      // Drag and drop events
      const dropZone = document.getElementById("cv-drop-zone");
      if (dropZone) {
        dropZone.addEventListener("dragover", (e) => this.handleDragOver(e));
        dropZone.addEventListener("dragleave", (e) => this.handleDragLeave(e));
        dropZone.addEventListener("drop", (e) => this.handleDrop(e));
      }

      // File input change
      $("#cv-file-input").on("change", (e) => this.handleFileSelect(e));

      // Browse button click
      $(".browse-btn").on("click", () => $("#cv-file-input").click());

      // Results actions
      $(document).on("click", ".btn-confirm-profile", () =>
        this.confirmProfile()
      );
      $(document).on("click", ".btn-edit-profile", () => this.editProfile());
      $(document).on("click", ".btn-retry", () => this.resetUpload());
    }

    handleDragOver(e) {
      e.preventDefault();
      e.stopPropagation();
      $("#cv-drop-zone").addClass("drag-over");
    }

    handleDragLeave(e) {
      e.preventDefault();
      e.stopPropagation();
      $("#cv-drop-zone").removeClass("drag-over");
    }

    handleDrop(e) {
      e.preventDefault();
      e.stopPropagation();
      $("#cv-drop-zone").removeClass("drag-over");

      const files = e.dataTransfer.files;
      if (files.length > 0) {
        this.processFile(files[0]);
      }
    }

    handleFileSelect(e) {
      const files = e.target.files;
      if (files.length > 0) {
        this.processFile(files[0]);
      }
    }

    processFile(file) {
      // Validate file
      const validation = this.validateFile(file);
      if (!validation.valid) {
        this.showError(validation.error);
        return;
      }

      this.uploadedFile = file;
      this.showProgress();

      // Extract text based on file type
      const fileType = file.name.split(".").pop().toLowerCase();

      if (fileType === "pdf") {
        this.extractPDFText(file);
      } else if (fileType === "docx") {
        this.extractDOCXText(file);
      } else if (fileType === "doc") {
        this.uploadToServer(file);
      }
    }

    validateFile(file) {
      // Check file size (10MB max)
      if (file.size > 10485760) {
        return { valid: false, error: "File size exceeds 10MB limit" };
      }

      // Check file type
      const allowedTypes = ["pdf", "doc", "docx"];
      const fileType = file.name.split(".").pop().toLowerCase();
      if (!allowedTypes.includes(fileType)) {
        return {
          valid: false,
          error: "Please upload a PDF, DOC, or DOCX file",
        };
      }

      return { valid: true };
    }

    async extractPDFText(file) {
      this.updateProgress("extract", "Extracting text from PDF...");

      try {
        // Use PDF.js to extract text
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        let fullText = "";

        for (let i = 1; i <= pdf.numPages; i++) {
          const page = await pdf.getPage(i);
          const textContent = await page.getTextContent();
          const pageText = textContent.items.map((item) => item.str).join(" ");
          fullText += pageText + "\n";
        }

        this.parseCV(fullText, file);
      } catch (error) {
        console.error("PDF extraction error:", error);
        // Fallback to server-side processing
        this.uploadToServer(file);
      }
    }

    async extractDOCXText(file) {
      this.updateProgress("extract", "Extracting text from Word document...");

      try {
        // Use Mammoth.js to extract text
        const arrayBuffer = await file.arrayBuffer();
        const result = await mammoth.extractRawText({
          arrayBuffer: arrayBuffer,
        });
        const text = result.value;

        this.parseCV(text, file);
      } catch (error) {
        console.error("DOCX extraction error:", error);
        // Fallback to server-side processing
        this.uploadToServer(file);
      }
    }

    parseCV(text, file) {
      this.updateProgress("parse", "Analyzing your CV...");

      // Basic client-side parsing
      const parsedData = this.basicParse(text);

      // Send to server for AI parsing
      this.sendForAIParsing(text, file, parsedData);
    }

    basicParse(text) {
      const data = {
        personal: {},
        experience: [],
        education: [],
        skills: [],
      };

      // Extract email
      const emailMatch = text.match(
        /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/
      );
      if (emailMatch) {
        data.personal.email = emailMatch[0];
      }

      // Extract phone
      const phoneMatch = text.match(
        /[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}/
      );
      if (phoneMatch) {
        data.personal.phone = phoneMatch[0];
      }

      // Extract LinkedIn
      const linkedinMatch = text.match(/linkedin\.com\/in\/[a-zA-Z0-9-]+/);
      if (linkedinMatch) {
        data.personal.linkedin = "https://" + linkedinMatch[0];
      }

      // Extract name (usually at the beginning)
      const lines = text.split("\n").filter((line) => line.trim());
      if (lines.length > 0) {
        // First non-empty line often contains the name
        const potentialName = lines[0].trim();
        if (potentialName.length < 50 && !potentialName.includes("@")) {
          data.personal.full_name = potentialName;
        }
      }

      return data;
    }

    sendForAIParsing(text, file, basicParsedData) {
      const formData = new FormData();
      formData.append("action", "sffc_upload_cv");
      formData.append("nonce", sffc_ajax.nonce || "");
      formData.append("cv_file", file);
      formData.append("extracted_text", text);
      formData.append("basic_parse", JSON.stringify(basicParsedData));

      $.ajax({
        url: sffc_ajax.ajax_url || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
          if (response.success) {
            this.handleParseSuccess(response.data);
          } else {
            this.showError(response.data.message || "Failed to parse CV");
          }
        },
        error: (xhr, status, error) => {
          console.error("Upload error:", error);
          this.showError("Failed to process CV. Please try again.");
        },
      });
    }

    uploadToServer(file) {
      this.updateProgress("upload", "Uploading CV...");

      const formData = new FormData();
      formData.append("action", "sffc_upload_cv");
      formData.append("nonce", sffc_ajax.nonce || "");
      formData.append("cv_file", file);

      $.ajax({
        url: sffc_ajax.ajax_url || "/wp-admin/admin-ajax.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        xhr: () => {
          const xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", (e) => {
            if (e.lengthComputable) {
              const percentComplete = (e.loaded / e.total) * 100;
              this.updateProgressBar(percentComplete);
            }
          });
          return xhr;
        },
        success: (response) => {
          if (response.success) {
            this.handleUploadSuccess(response.data);
          } else {
            this.showError(response.data.message || "Upload failed");
          }
        },
        error: (xhr, status, error) => {
          console.error("Upload error:", error);
          this.showError("Failed to upload CV. Please try again.");
        },
      });
    }

    handleUploadSuccess(data) {
      this.updateProgress("parse", "Processing your CV...");

      // Poll for parsing completion
      this.pollForResults(data.upload_id);
    }

    pollForResults(uploadId) {
      const pollInterval = setInterval(() => {
        $.ajax({
          url: sffc_ajax.ajax_url,
          type: "POST",
          data: {
            action: "sffc_get_parse_status",
            upload_id: uploadId,
            nonce: sffc_ajax.nonce,
          },
          success: (response) => {
            if (response.success) {
              if (response.data.status === "completed") {
                clearInterval(pollInterval);
                this.handleParseSuccess(response.data);
              } else if (response.data.status === "failed") {
                clearInterval(pollInterval);
                this.showError("Failed to parse CV. Please try manual entry.");
              }
            }
          },
        });
      }, 2000);

      // Stop polling after 30 seconds
      setTimeout(() => clearInterval(pollInterval), 30000);
    }

    handleParseSuccess(data) {
      this.updateProgress("complete", "CV parsed successfully!");
      this.parsedData = data.parsed_data || data;

      // Save parsed data immediately
      this.saveParsedProfile();

      // Trigger profile completion event
      $(document).trigger("cv:profile:completed", [this.parsedData]);

      // Show assessment directly
      setTimeout(() => {
        this.showSuccess();
      }, 1000);
    }

    showResults() {
      $(".cv-upload-zone, .cv-upload-progress").hide();
      $(".cv-parse-results").fadeIn();

      const resultsHtml = this.generateResultsHTML(this.parsedData);
      $(".results-grid").html(resultsHtml);
    }

    generateResultsHTML(data) {
      let html = "";

      // Personal Information
      if (data.personal) {
        html += `
                    <div class="result-section">
                        <h4>Personal Information</h4>
                        <div class="result-fields">
                            ${
                              data.personal.full_name
                                ? `<div class="field"><label>Name:</label><span>${data.personal.full_name}</span></div>`
                                : ""
                            }
                            ${
                              data.personal.email
                                ? `<div class="field"><label>Email:</label><span>${data.personal.email}</span></div>`
                                : ""
                            }
                            ${
                              data.personal.phone
                                ? `<div class="field"><label>Phone:</label><span>${data.personal.phone}</span></div>`
                                : ""
                            }
                            ${
                              data.personal.location
                                ? `<div class="field"><label>Location:</label><span>${data.personal.location}</span></div>`
                                : ""
                            }
                            ${
                              data.personal.linkedin
                                ? `<div class="field"><label>LinkedIn:</label><span>${data.personal.linkedin}</span></div>`
                                : ""
                            }
                        </div>
                    </div>
                `;
      }

      // Experience
      if (data.experience && data.experience.length > 0) {
        html += `
                    <div class="result-section">
                        <h4>Experience (${
                          data.experience.length
                        } positions found)</h4>
                        <div class="result-fields">
                            ${data.experience
                              .slice(0, 2)
                              .map(
                                (exp) => `
                                <div class="field">
                                    <label>${exp.title} at ${
                                  exp.company
                                }</label>
                                    <span>${exp.dates || ""}</span>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                    </div>
                `;
      }

      // Education
      if (data.education && data.education.length > 0) {
        html += `
                    <div class="result-section">
                        <h4>Education</h4>
                        <div class="result-fields">
                            ${data.education
                              .map(
                                (edu) => `
                                <div class="field">
                                    <label>${edu.degree}</label>
                                    <span>${edu.institution}</span>
                                </div>
                            `
                              )
                              .join("")}
                        </div>
                    </div>
                `;
      }

      // Skills
      if (data.skills && data.skills.length > 0) {
        html += `
                    <div class="result-section">
                        <h4>Skills (${data.skills.length} identified)</h4>
                        <div class="skill-tags">
                            ${data.skills
                              .slice(0, 10)
                              .map(
                                (skill) => `
                                <span class="skill-tag">${skill}</span>
                            `
                              )
                              .join("")}
                        </div>
                    </div>
                `;
      }

      return html;
    }

    confirmProfile() {
      // Save parsed data to profile
      this.saveParsedProfile();

      // Trigger profile completion
      $(document).trigger("cv:profile:completed", [this.parsedData]);

      // Redirect to application or show success
      this.showSuccess();
    }

    saveParsedProfile() {
      // Save to localStorage
      localStorage.setItem(
        "sffc_cv_parsed_profile",
        JSON.stringify(this.parsedData)
      );

      // Update global profile data
      if (window.profileBuilder) {
        window.profileBuilder.profileData = {
          ...window.profileBuilder.profileData,
          ...this.parsedData,
        };
      }

      // Save via AJAX
      $.ajax({
        url: sffc_ajax.ajax_url,
        type: "POST",
        data: {
          action: "sffc_save_parsed_profile",
          parsed_data: JSON.stringify(this.parsedData),
          nonce: sffc_ajax.nonce,
        },
      });
    }

    editProfile() {
      // Open profile builder with pre-filled data
      if (window.profileBuilder) {
        window.profileBuilder.profileData = this.parsedData;
        window.profileBuilder.open();
      }

      // Hide CV interface
      $("#cv-upload-interface").fadeOut();
    }

    showSuccess() {
      // Hide progress and show assessment
      $(".cv-upload-zone, .cv-upload-progress").hide();

      // Generate dynamic profile assessment
      const assessment = this.generateProfileAssessment();

      const assessmentHtml = `
                <div class="profile-assessment" style="padding: 20px; max-width: 600px; margin: 0 auto;">
                    <div style="text-align: center; margin-bottom: 25px;">
                        <div style="background: #10B981; width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"></path>
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            </svg>
                        </div>
                        <h3 style="color: #1a472a; margin: 0 0 8px 0; font-size: 20px;">Profile Assessment Complete</h3>
                        <p style="color: #666; margin: 0; font-size: 14px;">Based on your background, here's what I see:</p>
                    </div>

                    <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <!-- Strength -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" style="margin-right: 8px;">
                                    <path d="M9 12l2 2 4-4"></path>
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg>
                                <span style="font-weight: 600; color: #10B981; font-size: 14px;">STRENGTH</span>
                            </div>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">${assessment.strength}</p>
                        </div>

                        <!-- Gap -->
                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" style="margin-right: 8px;">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                <span style="font-weight: 600; color: #F59E0B; font-size: 14px;">DEVELOPMENT AREA</span>
                            </div>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">${assessment.gap}</p>
                        </div>

                        <!-- Opportunity -->
                        <div>
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2" style="margin-right: 8px;">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                                </svg>
                                <span style="font-weight: 600; color: #8B5CF6; font-size: 14px;">OPPORTUNITY</span>
                            </div>
                            <p style="margin: 0; color: #374151; line-height: 1.5;">${assessment.opportunity}</p>
                        </div>
                    </div>

                    <!-- Competitiveness Score -->
                    <div style="background: #f8f9fa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #374151; font-size: 14px;">Market Competitiveness</span>
                            <span style="font-weight: 700; color: #1a472a; font-size: 16px;">${assessment.competitivenessScore}%</span>
                        </div>
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                            <div style="background: ${assessment.competitivenessColor}; height: 100%; width: ${assessment.competitivenessScore}%; transition: width 0.3s ease;"></div>
                        </div>
                        <p style="margin: 8px 0 0 0; color: #6b7280; font-size: 12px;">${assessment.competitivenessLabel}</p>
                    </div>

                    <div style="text-align: center;">
                        <button class="btn-see-opportunities" onclick="window.sennaConversational?.generateJobMatches()" style="background: #10B981; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px; margin-right: 10px;">
                            See My Opportunities
                        </button>
                        <button class="btn-view-profile" style="background: #374151; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 14px;">
                            View Full Profile
                        </button>
                    </div>
                </div>
            `;

      $("#cv-upload-interface").html(assessmentHtml);

      // Bind success actions
      $(".btn-view-profile").on("click", () => {
        if (window.openProfileBuilder) {
          window.openProfileBuilder();
        }
      });

      $(".btn-start-applying").on("click", () => {
        window.location.href = "/opportunities/";
      });
    }

    generateProfileAssessment() {
      // Ensure we have parsed data
      if (!this.parsedData || Object.keys(this.parsedData).length === 0) {
        console.log("No parsed data available, using default assessment");
        return this.getDefaultAssessment();
      }

      console.log("Generating assessment from:", this.parsedData);

      const assessment = {
        strength: "",
        gap: "",
        opportunity: "",
        competitivenessScore: 0,
        competitivenessColor: "#10B981",
        competitivenessLabel: "",
      };

      // Analyze experience
      const experience = this.parsedData.experience || [];
      const skills = this.parsedData.skills || [];
      const education = this.parsedData.education || [];

      // Calculate years of experience
      let totalYears = 0;
      let financeYears = 0;
      let hasTopTierExp = false;
      let hasModelingExp = false;

      const topTierFirms = [
        "goldman",
        "morgan stanley",
        "jp morgan",
        "jpmorgan",
        "credit suisse",
        "ubs",
        "barclays",
        "deutsche bank",
        "citigroup",
        "citi",
        "blackstone",
        "kkr",
        "apollo",
        "carlyle",
        "bain capital",
        "tpg",
      ];
      const financeKeywords = [
        "investment banking",
        "private equity",
        "asset management",
        "hedge fund",
        "financial modeling",
        "valuation",
        "due diligence",
        "m&a",
        "mergers",
        "acquisitions",
      ];
      const modelingKeywords = [
        "excel",
        "financial modeling",
        "dcf",
        "lbo",
        "comps",
        "comparable companies",
        "precedent transactions",
        "valuation",
      ];

      experience.forEach((exp) => {
        const title = (exp.title || "").toLowerCase();
        const company = (exp.company || "").toLowerCase();
        const description = (
          exp.responsibilities ||
          exp.description ||
          ""
        ).toLowerCase();

        // Calculate years (rough estimation)
        const years = this.extractYearsFromExperience(exp);
        totalYears += years;

        // Check for finance experience
        const isFinanceRole = financeKeywords.some(
          (keyword) => title.includes(keyword) || description.includes(keyword)
        );
        if (isFinanceRole) {
          financeYears += years;
        }

        // Check for top tier firms
        if (topTierFirms.some((firm) => company.includes(firm))) {
          hasTopTierExp = true;
        }

        // Check for modeling experience
        if (
          modelingKeywords.some(
            (keyword) =>
              title.includes(keyword) || description.includes(keyword)
          )
        ) {
          hasModelingExp = true;
        }
      });

      // Check skills for modeling
      const skillsText = skills.join(" ").toLowerCase();
      if (modelingKeywords.some((keyword) => skillsText.includes(keyword))) {
        hasModelingExp = true;
      }

      // Generate strength assessment
      if (hasTopTierExp && financeYears >= 2) {
        assessment.strength = `Your ${financeYears} years at a top-tier firm gives you strong deal experience and credibility`;
      } else if (financeYears >= 3) {
        assessment.strength = `Your ${financeYears} years in finance provides solid industry foundation and technical skills`;
      } else if (hasTopTierExp) {
        assessment.strength = `Your experience at a prestigious firm demonstrates high-caliber training and standards`;
      } else if (financeYears >= 1) {
        assessment.strength = `Your finance background provides relevant industry exposure and foundational knowledge`;
      } else {
        assessment.strength = `Your analytical background and transferable skills show strong potential for finance roles`;
      }

      // Generate gap assessment
      if (!hasModelingExp && financeYears < 2) {
        assessment.gap = `You need more financial modeling depth and technical skills for PE Associate roles`;
      } else if (!hasModelingExp) {
        assessment.gap = `Strengthen your financial modeling and valuation skills to be more competitive`;
      } else if (financeYears < 2) {
        assessment.gap = `Additional deal experience would strengthen your profile for senior roles`;
      } else if (!hasTopTierExp && financeYears < 5) {
        assessment.gap = `Consider targeting mid-market funds where your profile aligns better initially`;
      } else {
        assessment.gap = `Focus on showcasing transaction experience and quantifiable deal impact`;
      }

      // Generate opportunity assessment
      let opportunityCount = 0;
      if (hasTopTierExp) opportunityCount += 2;
      if (financeYears >= 2) opportunityCount += 2;
      if (hasModelingExp) opportunityCount += 1;
      if (financeYears >= 1) opportunityCount += 1;

      if (opportunityCount >= 4) {
        assessment.opportunity = `5+ growth equity and mid-market PE firms are excellent matches for your profile`;
      } else if (opportunityCount >= 3) {
        assessment.opportunity = `3-4 mid-market PE firms would strongly consider your background`;
      } else if (opportunityCount >= 2) {
        assessment.opportunity = `2-3 smaller PE shops or growth equity funds align with your experience`;
      } else {
        assessment.opportunity = `Corporate development and PE-adjacent roles could be strong stepping stones`;
      }

      // Calculate competitiveness score
      let score = 30; // Base score
      if (hasTopTierExp) score += 25;
      if (financeYears >= 3) score += 20;
      else if (financeYears >= 2) score += 15;
      else if (financeYears >= 1) score += 10;
      if (hasModelingExp) score += 15;
      if (totalYears >= 5) score += 10;
      if (
        education.some((ed) => (ed.degree || "").toLowerCase().includes("mba"))
      )
        score += 5;

      assessment.competitivenessScore = Math.min(score, 95); // Cap at 95%

      // Set color and label based on score
      if (assessment.competitivenessScore >= 80) {
        assessment.competitivenessColor = "#10B981";
        assessment.competitivenessLabel = "Highly competitive for PE roles";
      } else if (assessment.competitivenessScore >= 65) {
        assessment.competitivenessColor = "#F59E0B";
        assessment.competitivenessLabel =
          "Competitive for mid-market opportunities";
      } else if (assessment.competitivenessScore >= 50) {
        assessment.competitivenessColor = "#EF4444";
        assessment.competitivenessLabel =
          "Consider building more experience first";
      } else {
        assessment.competitivenessColor = "#6B7280";
        assessment.competitivenessLabel =
          "Focus on PE-adjacent roles initially";
      }

      return assessment;
    }

    extractYearsFromExperience(exp) {
      // Simple year extraction - could be enhanced
      if (exp.startDate && exp.endDate) {
        const start = new Date(exp.startDate);
        const end =
          exp.endDate === "Present" ? new Date() : new Date(exp.endDate);
        return Math.max(0.5, (end - start) / (1000 * 60 * 60 * 24 * 365));
      }
      return 1; // Default to 1 year if dates unclear
    }

    getDefaultAssessment() {
      return {
        strength:
          "Your professional background shows analytical capabilities suitable for finance",
        gap: "Building financial modeling and deal experience would strengthen your profile",
        opportunity:
          "Entry-level finance roles and PE-adjacent positions could be good starting points",
        competitivenessScore: 45,
        competitivenessColor: "#6B7280",
        competitivenessLabel: "Building foundation for finance career",
      };
    }

    showProgress() {
      $(".cv-upload-zone").hide();
      $(".cv-upload-progress").fadeIn();
      $(".file-name").text(this.uploadedFile.name);
    }

    updateProgress(step, status) {
      $(".progress-status").text(status);
      $(".progress-steps .step").removeClass("active completed");

      const steps = ["upload", "extract", "parse", "complete"];
      const currentIndex = steps.indexOf(step);

      steps.forEach((s, index) => {
        if (index < currentIndex) {
          $(`.step[data-step="${s}"]`).addClass("completed");
        } else if (index === currentIndex) {
          $(`.step[data-step="${s}"]`).addClass("active");
        }
      });
    }

    updateProgressBar(percent) {
      $(".progress-fill").css("width", percent + "%");
    }

    showError(message) {
      $(".cv-upload-zone, .cv-upload-progress, .cv-parse-results").hide();
      $(".cv-error-message").fadeIn();
      $(".error-text").text(message);
    }

    resetUpload() {
      this.uploadedFile = null;
      this.parsedData = null;
      $(".cv-error-message, .cv-upload-progress, .cv-parse-results").hide();
      $(".cv-upload-zone").fadeIn();
      $("#cv-file-input").val("");
    }

    loadPDFJS() {
      if (typeof pdfjsLib === "undefined") {
        const script = document.createElement("script");
        script.src =
          "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js";
        document.head.appendChild(script);

        script.onload = () => {
          pdfjsLib.GlobalWorkerOptions.workerSrc =
            "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
        };
      }
    }

    loadMammoth() {
      if (typeof mammoth === "undefined") {
        const script = document.createElement("script");
        script.src =
          "https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js";
        document.head.appendChild(script);
      }
    }
  }

  // Initialize when ready
  $(document).ready(() => {
    window.cvUploadHandler = new CVUploadHandler();
  });
})(jQuery);
