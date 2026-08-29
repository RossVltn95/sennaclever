/**
 * WSJ CV Integration
 * Connects the WSJ CV renderer with existing chat interface and action cards
 * Replaces file upload with elegant text-based CV handling
 */

(function ($) {
  "use strict";

  // WSJ CV Integration Manager
  window.WSJCVIntegration = {
    renderer: null,
    isActive: false,
    currentJobContext: null,

    /**
     * Initialize WSJ CV integration
     */
    init: function () {
      console.log("WSJ CV Integration: Initializing...");

      // Load required scripts and styles
      this.loadDependencies();

      // Override existing CV functions
      this.overrideExistingFunctions();

      // Set up event listeners
      this.setupEventListeners();

      // Initialize renderer when ready
      this.initializeRenderer();

      console.log("WSJ CV Integration: Ready");
    },

    /**
     * Load dependencies
     */
    loadDependencies: function () {
      // Load WSJ CSS if not already loaded
      if (!document.querySelector('link[href*="wsj-cv-display.css"]')) {
        const cssLink = document.createElement("link");
        cssLink.rel = "stylesheet";
        cssLink.href = sffcAjax?.plugin_url
          ? sffcAjax.plugin_url + "/assets/css/wsj-cv-display.css"
          : "/wp-content/plugins/senna-finance-career/assets/css/wsj-cv-display.css";
        document.head.appendChild(cssLink);
      }

      // Load WSJ Renderer if not already loaded
      if (!window.WSJCVRenderer) {
        const script = document.createElement("script");
        script.src = sffcAjax?.plugin_url
          ? sffcAjax.plugin_url + "/assets/js/wsj-cv-renderer.js"
          : "/wp-content/plugins/senna-finance-career/assets/js/wsj-cv-renderer.js";
        document.head.appendChild(script);
      }
    },

    /**
     * Initialize the WSJ renderer
     */
    initializeRenderer: function () {
      // Wait for renderer to be available
      const checkRenderer = setInterval(() => {
        if (window.WSJCVRenderer) {
          clearInterval(checkRenderer);

          // Create container if it doesn't exist
          if (!document.querySelector(".wsj-cv-display-container")) {
            const container = document.createElement("div");
            container.className = "wsj-cv-display-container";
            container.style.display = "none";
            document.body.appendChild(container);
          }

          // Initialize renderer
          this.renderer = new WSJCVRenderer({
            container: ".wsj-cv-display-container",
            editable: true,
            autoSave: true,
            animations: true,
          });

          console.log("WSJ CV Renderer initialized");
        }
      }, 100);
    },

    /**
     * Override existing CV upload functions
     */
    overrideExistingFunctions: function () {
      const self = this;

      // Override the global tailorCV function
      if (window.tailorCV) {
        window.originalTailorCV = window.tailorCV;
      }

      // DISABLED: Using job-cards-interaction.js implementation instead
      /* window.tailorCV = function(jobId) {
                console.log('WSJ CV: Intercepted tailorCV call for job:', jobId);
                self.showWSJInterface(jobId);
            }; */

      // Override showCVUploadInterface
      if (window.showCVUploadInterface) {
        window.originalShowCVUploadInterface = window.showCVUploadInterface;
      }

      window.showCVUploadInterface = function () {
        console.log("WSJ CV: Intercepted CV upload request");
        self.showWSJInterface();
      };

      // Override CV action card handlers
      if (window.handleCVAction) {
        window.originalHandleCVAction = window.handleCVAction;
      }

      window.handleCVAction = function (action) {
        console.log("WSJ CV: Intercepted CV action:", action);
        self.showWSJInterface(null, action);
      };
    },

    /**
     * Show the WSJ CV interface
     */
    showWSJInterface: function (jobId = null, action = null) {
      const self = this;

      // Store job context if provided
      if (jobId) {
        this.currentJobContext = jobId;
      }

      // Create the WSJ interface HTML
      const interfaceHTML = `
                <div class="wsj-cv-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(26, 71, 42, 0.95);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    overflow-y: auto;
                    animation: fadeIn 0.3s ease-out;
                ">
                    <div class="wsj-cv-interface" style="
                        background: #faf7f2;
                        width: 100%;
                        max-width: 1400px;
                        height: 90vh;
                        border-radius: 12px;
                        display: flex;
                        overflow: hidden;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        animation: slideUp 0.4s ease-out;
                    ">
                        <!-- Left Panel: Input -->
                        <div class="wsj-cv-input-panel" style="
                            flex: 0 0 40%;
                            background: white;
                            padding: 32px;
                            overflow-y: auto;
                            border-right: 1px solid rgba(45, 106, 79, 0.1);
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                                <h2 style="
                                    font-family: 'Playfair Display', Georgia, serif;
                                    font-size: 28px;
                                    color: #1a472a;
                                    margin: 0;
                                ">Transform Your CV</h2>
                                <button onclick="WSJCVIntegration.close()" style="
                                    background: none;
                                    border: none;
                                    font-size: 28px;
                                    color: #666;
                                    cursor: pointer;
                                    padding: 0;
                                    width: 32px;
                                    height: 32px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    border-radius: 50%;
                                    transition: all 0.2s;
                                " onmouseover="this.style.background='rgba(0,0,0,0.05)'" 
                                   onmouseout="this.style.background='none'">×</button>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <p style="color: #666; line-height: 1.6; margin-bottom: 16px;">
                                    Paste your CV text below or start typing. Watch it transform into a professional, 
                                    WSJ-quality document in real-time.
                                </p>
                            </div>
                            
                            <!-- Quick Actions -->
                            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                                <button onclick="WSJCVIntegration.loadSample()" style="
                                    padding: 8px 16px;
                                    background: white;
                                    border: 1px solid #2d6a4f;
                                    color: #2d6a4f;
                                    border-radius: 20px;
                                    font-size: 13px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " onmouseover="this.style.background='#2d6a4f'; this.style.color='white';" 
                                   onmouseout="this.style.background='white'; this.style.color='#2d6a4f';">
                                    Load Sample CV
                                </button>
                                <button onclick="WSJCVIntegration.enhance()" style="
                                    padding: 8px 16px;
                                    background: white;
                                    border: 1px solid #2d6a4f;
                                    color: #2d6a4f;
                                    border-radius: 20px;
                                    font-size: 13px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " onmouseover="this.style.background='#2d6a4f'; this.style.color='white';" 
                                   onmouseout="this.style.background='white'; this.style.color='#2d6a4f';">
                                    AI Enhance
                                </button>
                                <button onclick="WSJCVIntegration.clear()" style="
                                    padding: 8px 16px;
                                    background: white;
                                    border: 1px solid #ddd;
                                    color: #999;
                                    border-radius: 20px;
                                    font-size: 13px;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " onmouseover="this.style.borderColor='#999';" 
                                   onmouseout="this.style.borderColor='#ddd';">
                                    Clear
                                </button>
                            </div>
                            
                            <!-- Text Input Area -->
                            <textarea id="wsj-cv-input" placeholder="Paste your CV here or start typing...

Example format:
John Smith
john.smith@email.com | +44 7700 900000 | London, UK

SUMMARY
Experienced professional with...

EXPERIENCE
Company Name - Location
Role Title - Jan 2020 - Present
• Achievement with specific metrics
• Another accomplishment

EDUCATION
University Name
Degree - Year

SKILLS
Python, Excel, Financial Modeling" style="
                                width: 100%;
                                height: calc(100% - 200px);
                                padding: 16px;
                                border: 2px solid rgba(45, 106, 79, 0.2);
                                border-radius: 8px;
                                font-family: -apple-system, BlinkMacSystemFont, sans-serif;
                                font-size: 14px;
                                line-height: 1.6;
                                resize: none;
                                transition: border-color 0.2s;
                            " onfocus="this.style.borderColor='#2d6a4f'" 
                               onblur="this.style.borderColor='rgba(45, 106, 79, 0.2)'"
                               oninput="WSJCVIntegration.handleInput(this.value)"></textarea>
                            
                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 20px;">
                                <button onclick="WSJCVIntegration.applyToJob()" style="
                                    flex: 1;
                                    padding: 12px 24px;
                                    background: linear-gradient(135deg, #1a472a, #2d6a4f);
                                    color: white;
                                    border: none;
                                    border-radius: 6px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(45,106,79,0.3)';" 
                                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    Apply Transformation
                                </button>
                                <button onclick="WSJCVIntegration.export()" style="
                                    padding: 12px 24px;
                                    background: white;
                                    color: #2d6a4f;
                                    border: 2px solid #2d6a4f;
                                    border-radius: 6px;
                                    font-weight: 600;
                                    cursor: pointer;
                                    transition: all 0.2s;
                                " onmouseover="this.style.background='#2d6a4f'; this.style.color='white';" 
                                   onmouseout="this.style.background='white'; this.style.color='#2d6a4f';">
                                    Export
                                </button>
                            </div>
                        </div>
                        
                        <!-- Right Panel: WSJ Preview -->
                        <div class="wsj-cv-preview-panel" style="
                            flex: 1;
                            background: #faf7f2;
                            overflow-y: auto;
                            position: relative;
                        ">
                            <!-- Preview Header -->
                            <div style="
                                position: sticky;
                                top: 0;
                                background: linear-gradient(to bottom, #faf7f2 80%, transparent);
                                padding: 20px 32px;
                                z-index: 10;
                            ">
                                <div style="display: flex; justify-content: between; align-items: center;">
                                    <h3 style="
                                        font-family: Georgia, serif;
                                        font-size: 18px;
                                        color: #1a472a;
                                        margin: 0;
                                        font-weight: normal;
                                        font-style: italic;
                                    ">Live Preview</h3>
                                    <div style="display: flex; gap: 8px; margin-left: auto;">
                                        <span style="
                                            padding: 4px 12px;
                                            background: rgba(45, 106, 79, 0.1);
                                            color: #2d6a4f;
                                            border-radius: 12px;
                                            font-size: 12px;
                                            font-weight: 500;
                                        ">WSJ Style</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- WSJ CV Display Container -->
                            <div class="wsj-cv-display-container" style="
                                padding: 0 32px 32px;
                                min-height: 500px;
                            "></div>
                        </div>
                    </div>
                </div>
            `;

      // Add interface to page
      const modalDiv = document.createElement("div");
      modalDiv.innerHTML = interfaceHTML;
      document.body.appendChild(modalDiv);

      // Initialize renderer in the preview panel
      setTimeout(() => {
        if (!this.renderer) {
          this.renderer = new WSJCVRenderer({
            container: ".wsj-cv-display-container",
            editable: true,
            autoSave: true,
            animations: true,
          });
        }

        // Load existing CV if available
        this.loadExistingCV();
      }, 100);

      // Mark as active
      this.isActive = true;

      // Add styles for animations
      this.addAnimationStyles();
    },

    /**
     * Handle input changes
     */
    handleInput: function (text) {
      if (this.renderer) {
        // Debounce updates for performance
        clearTimeout(this.inputTimeout);
        this.inputTimeout = setTimeout(() => {
          this.renderer.updateFromText(text);
        }, 300);
      }
    },

    /**
     * Load existing CV data
     */
    loadExistingCV: function () {
      // Check if user has CV in session or database
      const savedCV = localStorage.getItem("wsj_cv_data");
      if (savedCV) {
        try {
          const cvData = JSON.parse(savedCV);
          this.renderer.setData(cvData);

          // Also populate the text area
          const textArea = document.getElementById("wsj-cv-input");
          if (textArea) {
            textArea.value = this.renderer.exportText();
          }
        } catch (e) {
          console.log("No saved CV data");
        }
      }
    },

    /**
     * Load sample CV
     */
    loadSample: function () {
      const sampleCV = `Ropa Ushe
ropayashe@gmail.com | +44 7765283181 | London, UK

SUMMARY
Accomplished finance professional with comprehensive experience across investment banking, private equity, and asset management. Proven track record of delivering complex financial analysis, building sophisticated models, and driving strategic initiatives.

EXPERIENCE

ETFS Capital - Family Office
Origination & Strategy Analyst
June 2022 - October 2022
• Analyzed comprehensive financial models for the Tuckwell Education Foundation
• Delivered portfolio attribution & benchmark analysis for ETFS Capital Portfolios
• Created automated scholarship filtering system improving efficiency by 40%
• Evaluated 20+ venture capital & private equity investment opportunities

Triple Point
Portfolio Risk Analyst  
June 2021 - June 2022
• Built and maintained financial models using VBA and Python for $100M+ portfolio
• Led automation projects across Digital Infrastructure, Risk and VC teams
• Reduced portfolio reporting time by 50% through process automation
• Delivered market reports to portfolio managers using Bloomberg and Reuters data

Bank of America
Global Markets Analyst
September 2020 - June 2021
• Executed complex FX and rates trades with daily volumes exceeding $50M
• Developed customized hedging strategies for corporate clients
• Contributed to $10M+ in new business through client engagement
• Analyzed market risk exposure across multiple asset classes

EDUCATION

Royal Holloway, University of London
BSc Business and Management - First Class Honours
September 2015 - July 2019

CFA Institute
CFA Level 1 Candidate
June 2023 - Present

SKILLS
Financial Modeling, VBA, Python, Excel, Bloomberg Terminal, Capital IQ, Portfolio Analysis, Risk Management`;

      const textArea = document.getElementById("wsj-cv-input");
      if (textArea) {
        textArea.value = sampleCV;
        this.handleInput(sampleCV);
      }
    },

    /**
     * Enhance CV with AI
     */
    enhance: function () {
      // Send to MENA Careers for enhancement
      if (window.SennaChat && window.SennaChat.send) {
        const currentText =
          document.getElementById("wsj-cv-input")?.value || "";
        const prompt = `Please enhance this CV with stronger action verbs and metrics: ${currentText}`;

        window.SennaChat.send(prompt);
        this.close();
      }
    },

    /**
     * Clear input
     */
    clear: function () {
      const textArea = document.getElementById("wsj-cv-input");
      if (textArea) {
        textArea.value = "";
        if (this.renderer) {
          this.renderer.render({
            name: "",
            title: "",
            contact: {},
            summary: "",
            experience: [],
            education: [],
            skills: [],
          });
        }
      }
    },

    /**
     * Apply transformation to job
     */
    applyToJob: function () {
      if (this.renderer) {
        const cvData = this.renderer.getData();

        // Save to localStorage
        localStorage.setItem("wsj_cv_data", JSON.stringify(cvData));

        // Send to chat if job context exists
        if (this.currentJobContext) {
          const message = `I've prepared my CV. Please tailor it for job ${this.currentJobContext}`;
          if (window.SennaChat && window.SennaChat.send) {
            window.SennaChat.send(message);
          }
        }

        // Show success message
        this.showSuccess();
      }
    },

    /**
     * Export CV
     */
    export: function () {
      if (this.renderer) {
        const text = this.renderer.exportText();

        // Create download
        const blob = new Blob([text], { type: "text/plain" });
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "cv-wsj-style.txt";
        a.click();
        URL.revokeObjectURL(url);
      }
    },

    /**
     * Show success message
     */
    showSuccess: function () {
      const successDiv = document.createElement("div");
      successDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #2d6a4f;
                color: white;
                padding: 20px 40px;
                border-radius: 8px;
                font-weight: 600;
                z-index: 10001;
                animation: successPulse 0.6s ease-out;
            `;
      successDiv.textContent = "✓ CV Transformed Successfully";
      document.body.appendChild(successDiv);

      setTimeout(() => {
        successDiv.remove();
        this.close();
      }, 2000);
    },

    /**
     * Close the interface
     */
    close: function () {
      const modal = document.querySelector(".wsj-cv-modal");
      if (modal) {
        modal.style.animation = "fadeOut 0.3s ease-out";
        setTimeout(() => {
          modal.parentElement.remove();
        }, 300);
      }
      this.isActive = false;
    },

    /**
     * Add animation styles
     */
    addAnimationStyles: function () {
      if (!document.getElementById("wsj-animations")) {
        const style = document.createElement("style");
        style.id = "wsj-animations";
        style.textContent = `
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes fadeOut {
                        from { opacity: 1; }
                        to { opacity: 0; }
                    }
                    @keyframes slideUp {
                        from { 
                            opacity: 0;
                            transform: translateY(20px);
                        }
                        to { 
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    @keyframes successPulse {
                        0% { 
                            transform: translate(-50%, -50%) scale(0.8);
                            opacity: 0;
                        }
                        50% { 
                            transform: translate(-50%, -50%) scale(1.1);
                        }
                        100% { 
                            transform: translate(-50%, -50%) scale(1);
                            opacity: 1;
                        }
                    }
                    
                    /* Mobile responsive */
                    @media (max-width: 768px) {
                        .wsj-cv-interface {
                            flex-direction: column !important;
                            height: 100vh !important;
                            border-radius: 0 !important;
                        }
                        .wsj-cv-input-panel {
                            flex: none !important;
                            height: 40vh !important;
                            border-right: none !important;
                            border-bottom: 1px solid rgba(45, 106, 79, 0.1) !important;
                        }
                        .wsj-cv-preview-panel {
                            height: 60vh !important;
                        }
                    }
                `;
        document.head.appendChild(style);
      }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function () {
      // Listen for CV-related events
      document.addEventListener(
        "click",
        (e) => {
          // Intercept tailor CV buttons
          if (
            e.target.matches(
              '.tailor-cv-main-btn, .tailor-cv-btn, [data-action="tailor-cv"]'
            )
          ) {
            e.preventDefault();
            e.stopPropagation();

            const jobId = e.target.dataset.jobId;
            this.showWSJInterface(jobId);
          }

          // Intercept CV upload buttons
          if (e.target.matches('.cv-upload-btn, [data-action="upload-cv"]')) {
            e.preventDefault();
            e.stopPropagation();
            this.showWSJInterface();
          }
        },
        true
      );

      // Listen for keyboard shortcuts
      document.addEventListener("keydown", (e) => {
        // Ctrl/Cmd + Shift + C to open CV interface
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === "C") {
          e.preventDefault();
          this.showWSJInterface();
        }

        // Escape to close
        if (e.key === "Escape" && this.isActive) {
          this.close();
        }
      });
    },
  };

  // Initialize when DOM is ready
  $(document).ready(function () {
    WSJCVIntegration.init();
  });

  // Also initialize on Turbo/Turbolinks load
  $(document).on("turbo:load turbolinks:load", function () {
    WSJCVIntegration.init();
  });
})(jQuery);
