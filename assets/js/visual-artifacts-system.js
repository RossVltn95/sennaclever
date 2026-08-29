/**
 * Visual Artifacts System
 * Structured interfaces for action cards - European PE Platform
 */

(function ($) {
  "use strict";

  class VisualArtifactsSystem {
    constructor() {
      this.currentArtifact = null;
      this.artifactData = {};
      this.currency = "GBP"; // Default to GBP for UK/European audience
      this.init();
    }

    init() {
      this.bindEvents();
      this.initializeArtifactTypes();
    }

    bindEvents() {
      // Prevent clicks on artifact container from bubbling
      $(document).on("click", ".visual-artifact-container", function (e) {
        e.stopPropagation();
      });

      // Close button
      $(document).on("click", ".va-close", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.closeArtifact();
      });

      // Dropdown interactions
      $(document).on("click", ".va-dropdown-trigger", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const dropdown = $(this).closest(".va-dropdown");
        dropdown.toggleClass("active");
      });

      $(document).on("click", ".va-dropdown-item", function () {
        const dropdown = $(this).closest(".va-dropdown");
        const trigger = dropdown.find(".va-dropdown-trigger");
        const value = $(this).data("value");
        const text = $(this).text();

        trigger.text(text);
        trigger.data("value", value);
        dropdown.removeClass("active");
      });

      // Close dropdowns when clicking outside
      $(document).click((e) => {
        // Only close dropdowns if not clicking on a dropdown element
        if (!$(e.target).closest(".va-dropdown").length) {
          $(".va-dropdown").removeClass("active");
        }
      });

      // Tag selector
      $(document).on("click", ".va-tag", function () {
        $(this).toggleClass("selected");
      });

      // Currency toggle
      $(document).on("click", ".va-currency-option", (e) => {
        const currency = $(e.target).data("currency");
        this.setCurrency(currency);
      });

      // Form submissions
      $(document).on("click", ".va-submit", (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.handleSubmit();
      });

      // Tab switching
      $(document).on("click", ".va-tab", function () {
        const tabId = $(this).data("tab");
        $(".va-tab").removeClass("active");
        $(".va-tab-content").removeClass("active");
        $(this).addClass("active");
        $(`#${tabId}`).addClass("active");
      });
    }

    initializeArtifactTypes() {
      this.artifacts = {
        // CV & Profile Actions
        "cv-tailor": this.createCVTailoringArtifact.bind(this),
        "cv-analyze": this.createCVAnalysisArtifact.bind(this),
        "cv-optimize": this.createCVOptimizationArtifact.bind(this),
        "cv-enhance": this.createCVEnhancementArtifact.bind(this),

        // Research & Analysis
        research: this.createMarketResearchArtifact.bind(this),
        "market-research": this.createMarketResearchArtifact.bind(this), // Test page alias
        analyze: this.createDealAnalysisArtifact.bind(this),
        "deal-analysis": this.createDealAnalysisArtifact.bind(this), // Test page alias
        assess: this.createAssessmentArtifact.bind(this),
        evaluate: this.createEvaluationArtifact.bind(this),

        // Planning & Strategy
        plan: this.createPlanningArtifact.bind(this),
        strategy: this.createFundStrategyArtifact.bind(this),
        "fund-strategy": this.createFundStrategyArtifact.bind(this), // Test page alias
        map: this.createNetworkMappingArtifact.bind(this),
        "network-mapping": this.createNetworkMappingArtifact.bind(this), // Test page alias

        // Career Development
        prepare: this.createInterviewPrepArtifact.bind(this),
        "interview-prep": this.createInterviewPrepArtifact.bind(this), // Test page alias
        guide: this.createCareerGuideArtifact.bind(this),
        develop: this.createDevelopmentArtifact.bind(this),

        // LinkedIn & Profile
        audit: this.createLinkedInAuditArtifact.bind(this),
        highlight: this.createExpertiseHighlightArtifact.bind(this),
        craft: this.createStoryCraftingArtifact.bind(this),

        // Communication
        respond: this.createResponseArtifact.bind(this),
        request: this.createRequestArtifact.bind(this),

        // Negotiation & Compensation
        negotiate: this.createCompensationArtifact.bind(this),
        compensation: this.createCompensationArtifact.bind(this), // Test page alias
        calculate: this.createCalculatorArtifact.bind(this),

        // Content Creation
        build: this.createBuilderArtifact.bind(this),
        create: this.createCreatorArtifact.bind(this),
        generate: this.createGeneratorArtifact.bind(this),

        // Search & Exploration
        search: this.createSearchArtifact.bind(this),
        explore: this.createExplorationArtifact.bind(this),
        find: this.createFinderArtifact.bind(this),

        // Portfolio
        "portfolio-review": this.createPortfolioReviewArtifact.bind(this), // Test page alias

        // Default fallbacks
        default: this.createDefaultArtifact.bind(this),
      };
    }

    openArtifact(type, data = {}) {
      console.log("Opening artifact:", type, data);

      // Remove existing artifact if any
      this.closeArtifact();

      // Store current type and data
      this.currentArtifact = type;
      this.artifactData = data;

      // Create the artifact based on type
      let artifactHTML;
      try {
        // Check if we have a specific handler for this type
        if (this.artifacts[type]) {
          artifactHTML = this.artifacts[type](data);
        } else {
          // Try to find a fallback based on the type
          console.log("No specific handler for type:", type, "using default");
          artifactHTML = this.createDefaultArtifact(data);
        }
      } catch (error) {
        console.error("Error creating artifact:", error);
        artifactHTML = this.createDefaultArtifact(data);
      }

      // Add to DOM
      $("body").append(artifactHTML);

      // Animate in
      setTimeout(() => {
        $(".visual-artifact-container").addClass("active");
      }, 50);
    }

    closeArtifact() {
      const $container = $(".visual-artifact-container");
      if ($container.length) {
        $container.removeClass("active");
        setTimeout(() => {
          $container.remove();
        }, 300);
      }
      this.currentArtifact = null;
    }

    createCVTailoringArtifact(data) {
      const hasCV = data.hasCVUploaded || false;

      if (!hasCV) {
        return this.createCVUploadPrompt();
      }

      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>CV Tailoring Parameters</h3>
                        <p>Customize your CV for specific opportunities</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Target Role</label>
                            <input type="text" class="va-form-input" id="va-target-role" 
                                placeholder="e.g., Senior Investment Associate">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Company Type</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Company Type</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="pe">Private Equity</div>
                                    <div class="va-dropdown-item" data-value="vc">Venture Capital</div>
                                    <div class="va-dropdown-item" data-value="ib">Investment Banking</div>
                                    <div class="va-dropdown-item" data-value="pc">Private Credit</div>
                                    <div class="va-dropdown-item" data-value="am">Asset Management</div>
                                    <div class="va-dropdown-item" data-value="corporate">Corporate Development</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Seniority Level</label>
                            <div class="va-slider-container">
                                <div class="va-slider-track">
                                    <div class="va-slider-fill"></div>
                                    <div class="va-slider-handle" style="left: 40%;"></div>
                                </div>
                                <div class="va-slider-labels">
                                    <span class="va-slider-label">Analyst</span>
                                    <span class="va-slider-label">Associate</span>
                                    <span class="va-slider-label">VP</span>
                                    <span class="va-slider-label">Director</span>
                                    <span class="va-slider-label">Partner</span>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Key Skills to Highlight</label>
                            <div class="va-tags-container">
                                <span class="va-tag">Financial Modelling</span>
                                <span class="va-tag">Due Diligence</span>
                                <span class="va-tag">Deal Sourcing</span>
                                <span class="va-tag">Portfolio Management</span>
                                <span class="va-tag">Valuation</span>
                                <span class="va-tag">LBO Analysis</span>
                                <span class="va-tag">Market Research</span>
                                <span class="va-tag">ESG Integration</span>
                                <span class="va-tag">Fundraising</span>
                                <span class="va-tag">LP Relations</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Generate Tailored CV</button>
                    </div>
                </div>
            `;
    }

    createCVUploadPrompt() {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>CV Analysis Preparation</h3>
                        <p>Let's optimize your CV for PE opportunities</p>
                    </div>
                    <div class="va-content" style="text-align: center; padding: 40px 24px;">
                        <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: var(--va-gradient); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="15" y2="15"></line>
                            </svg>
                        </div>
                        <p style="color: var(--va-black); font-size: 16px; margin-bottom: 8px;">Please share your CV in the chat</p>
                        <p style="color: var(--va-gray); font-size: 14px; line-height: 1.5;">Upload your current CV to begin tailored optimization for your target roles</p>
                        <button class="va-button-primary" style="margin-top: 32px;" onclick="window.visualArtifacts.promptCVUpload()">I've Shared My CV</button>
                    </div>
                </div>
            `;
    }

    createMarketResearchArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Market Research Parameters</h3>
                        <p>Define your research scope and depth</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Research Scope</label>
                            <input type="text" class="va-form-input" id="va-research-scope" 
                                placeholder="e.g., European SaaS landscape, UK fintech market">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Analysis Depth</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                                <div class="va-depth-card" data-depth="quick" style="padding: 16px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Quick Review</div>
                                    <div style="font-size: 12px; color: var(--va-gray);">5-10 min read</div>
                                </div>
                                <div class="va-depth-card" data-depth="standard" style="padding: 16px; background: var(--va-gradient); color: white; border: 1px solid transparent; border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Standard Analysis</div>
                                    <div style="font-size: 12px; opacity: 0.9;">15-20 min read</div>
                                </div>
                                <div class="va-depth-card" data-depth="deep" style="padding: 16px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Deep Dive</div>
                                    <div style="font-size: 12px; color: var(--va-gray);">30+ min read</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Geographic Focus</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Region</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="uk">United Kingdom</div>
                                    <div class="va-dropdown-item" data-value="dach">DACH (Germany, Austria, Switzerland)</div>
                                    <div class="va-dropdown-item" data-value="nordics">Nordics</div>
                                    <div class="va-dropdown-item" data-value="benelux">Benelux</div>
                                    <div class="va-dropdown-item" data-value="france">France</div>
                                    <div class="va-dropdown-item" data-value="iberia">Iberia (Spain & Portugal)</div>
                                    <div class="va-dropdown-item" data-value="cee">Central & Eastern Europe</div>
                                    <div class="va-dropdown-item" data-value="pan-eu">Pan-European</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Sector Filter</label>
                            <div class="va-tags-container">
                                <span class="va-tag">Technology</span>
                                <span class="va-tag">Healthcare</span>
                                <span class="va-tag">Financial Services</span>
                                <span class="va-tag">Consumer</span>
                                <span class="va-tag">Industrials</span>
                                <span class="va-tag">Energy & Clean Tech</span>
                                <span class="va-tag">Real Estate</span>
                                <span class="va-tag">Infrastructure</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Begin Research</button>
                    </div>
                </div>
            `;
    }

    createDealAnalysisArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Deal Analysis Workbench</h3>
                        <p>Structure your investment analysis</p>
                    </div>
                    <div class="va-content">
                        <div class="va-steps">
                            <div class="va-step active">
                                <span class="va-step-number">1</span>
                            </div>
                            <div class="va-step">
                                <span class="va-step-number">2</span>
                            </div>
                            <div class="va-step">
                                <span class="va-step-number">3</span>
                            </div>
                        </div>

                        <div class="va-step-content" id="step-1">
                            <div class="va-form-group">
                                <label class="va-form-label">Deal Size</label>
                                <div class="va-currency-group">
                                    <span class="va-currency-symbol">${
                                      this.currency === "EUR" ? "€" : "£"
                                    }</span>
                                    <input type="text" class="va-form-input va-currency-input" id="va-deal-size" 
                                        placeholder="0">
                                    <div class="va-currency-toggle">
                                        <button class="va-currency-option ${
                                          this.currency === "GBP"
                                            ? "active"
                                            : ""
                                        }" data-currency="GBP">GBP</button>
                                        <button class="va-currency-option ${
                                          this.currency === "EUR"
                                            ? "active"
                                            : ""
                                        }" data-currency="EUR">EUR</button>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; margin-top: 8px;">
                                    <button class="va-quick-amount" data-amount="10000000" style="flex: 1; padding: 8px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; font-size: 12px; cursor: pointer;">10M</button>
                                    <button class="va-quick-amount" data-amount="50000000" style="flex: 1; padding: 8px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; font-size: 12px; cursor: pointer;">50M</button>
                                    <button class="va-quick-amount" data-amount="100000000" style="flex: 1; padding: 8px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; font-size: 12px; cursor: pointer;">100M</button>
                                    <button class="va-quick-amount" data-amount="500000000" style="flex: 1; padding: 8px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; font-size: 12px; cursor: pointer;">500M</button>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Investment Stage</label>
                                <div class="va-dropdown">
                                    <button class="va-dropdown-trigger">Select Stage</button>
                                    <div class="va-dropdown-menu">
                                        <div class="va-dropdown-item" data-value="seed">Seed</div>
                                        <div class="va-dropdown-item" data-value="series-a">Series A</div>
                                        <div class="va-dropdown-item" data-value="series-b">Series B</div>
                                        <div class="va-dropdown-item" data-value="series-c">Series C+</div>
                                        <div class="va-dropdown-item" data-value="growth">Growth Equity</div>
                                        <div class="va-dropdown-item" data-value="buyout">Buyout</div>
                                        <div class="va-dropdown-item" data-value="secondary">Secondary</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Continue Analysis</button>
                    </div>
                </div>
            `;
    }

    createCompensationArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Compensation Benchmarking</h3>
                        <p>Analyze your package against market standards</p>
                    </div>
                    <div class="va-content">
                        <div style="background: var(--va-cream-light); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                            <h4 style="margin: 0 0 16px; font-size: 14px; color: var(--va-black);">Current Package</h4>
                            
                            <div class="va-form-group">
                                <label class="va-form-label">Base Salary</label>
                                <div class="va-currency-group">
                                    <span class="va-currency-symbol">${
                                      this.currency === "EUR" ? "€" : "£"
                                    }</span>
                                    <input type="text" class="va-form-input va-currency-input" id="va-base-salary" 
                                        placeholder="0">
                                    <div class="va-currency-toggle">
                                        <button class="va-currency-option ${
                                          this.currency === "GBP"
                                            ? "active"
                                            : ""
                                        }" data-currency="GBP">GBP</button>
                                        <button class="va-currency-option ${
                                          this.currency === "EUR"
                                            ? "active"
                                            : ""
                                        }" data-currency="EUR">EUR</button>
                                    </div>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Bonus Target (%)</label>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <input type="range" min="0" max="200" value="50" class="va-range-input" id="va-bonus-target" style="flex: 1;">
                                    <span id="va-bonus-display" style="min-width: 40px; text-align: right;">50%</span>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Carried Interest</label>
                                <div style="display: flex; gap: 12px;">
                                    <button class="va-carry-option" data-carry="none" style="flex: 1; padding: 10px; background: var(--va-cream); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">None</button>
                                    <button class="va-carry-option active" data-carry="standard" style="flex: 1; padding: 10px; background: var(--va-gradient); color: white; border: none; border-radius: 6px; cursor: pointer;">Standard</button>
                                    <button class="va-carry-option" data-carry="enhanced" style="flex: 1; padding: 10px; background: var(--va-cream); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">Enhanced</button>
                                </div>
                            </div>
                        </div>

                        <div style="background: var(--va-cream-light); padding: 16px; border-radius: 8px;">
                            <h4 style="margin: 0 0 16px; font-size: 14px; color: var(--va-black);">Market Comparison</h4>
                            
                            <div class="va-form-group">
                                <label class="va-form-label">Location</label>
                                <div class="va-dropdown">
                                    <button class="va-dropdown-trigger">Select City</button>
                                    <div class="va-dropdown-menu">
                                        <div class="va-dropdown-item" data-value="dubai">Dubai</div>
                                        <div class="va-dropdown-item" data-value="abu-dhabi">Abu Dhabi</div>
                                        <div class="va-dropdown-item" data-value="riyadh">Riyadh</div>
                                        <div class="va-dropdown-item" data-value="cairo">Cairo</div>
                                        <div class="va-dropdown-item" data-value="doha">Doha</div>
                                        <div class="va-dropdown-item" data-value="manama">Manama</div>
                                        <div class="va-dropdown-item" data-value="jeddah">Jeddah</div>
                                        <div class="va-dropdown-item" data-value="muscat">Muscat</div>
                                        <div class="va-dropdown-item" data-value="kuwait-city">Kuwait City</div>
                                        <div class="va-dropdown-item" data-value="madrid">Madrid</div>
                                    </div>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Years of Experience</label>
                                <input type="number" class="va-form-input" id="va-experience" min="0" max="30" placeholder="0">
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Generate Benchmark Analysis</button>
                    </div>
                </div>
            `;
    }

    createInterviewPrepArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Interview Preparation Suite</h3>
                        <p>Structured preparation for your interview</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Firm Name</label>
                            <input type="text" class="va-form-input" id="va-firm-name" 
                                placeholder="e.g., Blackstone, KKR, Carlyle">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Interview Stage</label>
                            <div style="display: flex; background: var(--va-cream-light); border-radius: 8px; padding: 4px;">
                                <button class="va-stage-btn active" data-stage="screening" style="flex: 1; padding: 10px; background: var(--va-gradient); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">Screening</button>
                                <button class="va-stage-btn" data-stage="first" style="flex: 1; padding: 10px; background: transparent; border: none; cursor: pointer; font-size: 13px;">First Round</button>
                                <button class="va-stage-btn" data-stage="final" style="flex: 1; padding: 10px; background: transparent; border: none; cursor: pointer; font-size: 13px;">Final</button>
                                <button class="va-stage-btn" data-stage="partner" style="flex: 1; padding: 10px; background: transparent; border: none; cursor: pointer; font-size: 13px;">Partner</button>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Interview Format</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                                <div class="va-format-card" data-format="video" style="padding: 20px; background: var(--va-cream-light); border: 2px solid var(--va-primary-dark); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <svg width="24" height="24" style="margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 7l-7 5 7 5V7z"></path>
                                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                    </svg>
                                    <div style="font-size: 13px;">Video</div>
                                </div>
                                <div class="va-format-card" data-format="person" style="padding: 20px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <svg width="24" height="24" style="margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <div style="font-size: 13px;">In-Person</div>
                                </div>
                                <div class="va-format-card" data-format="phone" style="padding: 20px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <svg width="24" height="24" style="margin-bottom: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                    </svg>
                                    <div style="font-size: 13px;">Phone</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Preparation Focus</label>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" checked style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Technical Questions & Case Studies</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" checked style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Behavioural & Fit Questions</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Market & Industry Knowledge</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Firm-Specific Research</span>
                                </label>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Generate Preparation Plan</button>
                    </div>
                </div>
            `;
    }

    createFundStrategyArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Fund Strategy Analyzer</h3>
                        <p>Evaluate fund strategies and opportunities</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Select Fund Category</label>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div class="va-fund-card" data-fund="buyout" style="padding: 16px; background: var(--va-gradient); color: white; border-radius: 8px; cursor: pointer;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Buyout</div>
                                    <div style="font-size: 12px; opacity: 0.9;">€500M - €10B+</div>
                                </div>
                                <div class="va-fund-card" data-fund="growth" style="padding: 16px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Growth Equity</div>
                                    <div style="font-size: 12px; color: var(--va-gray);">€100M - €2B</div>
                                </div>
                                <div class="va-fund-card" data-fund="venture" style="padding: 16px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Venture Capital</div>
                                    <div style="font-size: 12px; color: var(--va-gray);">€50M - €500M</div>
                                </div>
                                <div class="va-fund-card" data-fund="infra" style="padding: 16px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">Infrastructure</div>
                                    <div style="font-size: 12px; color: var(--va-gray);">€1B - €20B+</div>
                                </div>
                            </div>
                        </div>

                        <div id="fund-details" style="display: block;">
                            <div class="va-form-group">
                                <label class="va-form-label">Target Fund Size</label>
                                <div class="va-currency-group">
                                    <span class="va-currency-symbol">${
                                      this.currency === "EUR" ? "€" : "£"
                                    }</span>
                                    <input type="text" class="va-form-input va-currency-input" id="va-fund-size" 
                                        placeholder="0">
                                    <div class="va-currency-toggle">
                                        <button class="va-currency-option ${
                                          this.currency === "GBP"
                                            ? "active"
                                            : ""
                                        }" data-currency="GBP">GBP</button>
                                        <button class="va-currency-option ${
                                          this.currency === "EUR"
                                            ? "active"
                                            : ""
                                        }" data-currency="EUR">EUR</button>
                                    </div>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Geographic Focus</label>
                                <div class="va-tags-container">
                                    <span class="va-tag selected">Pan-European</span>
                                    <span class="va-tag">UK & Ireland</span>
                                    <span class="va-tag">DACH</span>
                                    <span class="va-tag">Nordics</span>
                                    <span class="va-tag">Southern Europe</span>
                                    <span class="va-tag">CEE</span>
                                </div>
                            </div>

                            <div class="va-form-group">
                                <label class="va-form-label">Sector Focus</label>
                                <div class="va-tags-container">
                                    <span class="va-tag">Tech & Software</span>
                                    <span class="va-tag">Healthcare</span>
                                    <span class="va-tag">Consumer</span>
                                    <span class="va-tag">B2B Services</span>
                                    <span class="va-tag">Industrials</span>
                                    <span class="va-tag">Financial Services</span>
                                </div>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Analyze Strategy</button>
                    </div>
                </div>
            `;
    }

    createNetworkMappingArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Network Mapping Tool</h3>
                        <p>Build strategic connections in PE</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Target Firms</label>
                            <input type="text" class="va-form-input" id="va-target-firms" 
                                placeholder="e.g., Permira, CVC, EQT">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Connection Level</label>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                                <div class="va-level-card active" data-level="direct" style="padding: 12px; background: var(--va-gradient); color: white; border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-size: 20px; margin-bottom: 4px;">1°</div>
                                    <div style="font-size: 12px;">Direct</div>
                                </div>
                                <div class="va-level-card" data-level="second" style="padding: 12px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-size: 20px; margin-bottom: 4px;">2°</div>
                                    <div style="font-size: 12px;">Via Mutual</div>
                                </div>
                                <div class="va-level-card" data-level="third" style="padding: 12px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 8px; cursor: pointer; text-align: center;">
                                    <div style="font-size: 20px; margin-bottom: 4px;">3°</div>
                                    <div style="font-size: 12px;">Extended</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Connection Purpose</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Purpose</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="job">Job Opportunities</div>
                                    <div class="va-dropdown-item" data-value="deal">Deal Sourcing</div>
                                    <div class="va-dropdown-item" data-value="coinvest">Co-investment</div>
                                    <div class="va-dropdown-item" data-value="lp">LP Relations</div>
                                    <div class="va-dropdown-item" data-value="advisor">Advisory</div>
                                    <div class="va-dropdown-item" data-value="mentor">Mentorship</div>
                                </div>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Map Connections</button>
                    </div>
                </div>
            `;
    }

    createPortfolioReviewArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Portfolio Review Dashboard</h3>
                        <p>Comprehensive portfolio analysis</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Portfolio Composition</label>
                            <div style="background: var(--va-cream-light); padding: 16px; border-radius: 8px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                    <span style="font-size: 14px;">Number of Companies</span>
                                    <input type="number" min="1" max="100" value="12" style="width: 60px; padding: 4px 8px; border: 1px solid var(--va-border); border-radius: 4px;">
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span style="font-size: 14px;">Total AUM</span>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span>${
                                          this.currency === "EUR" ? "€" : "£"
                                        }</span>
                                        <input type="text" value="2,500,000,000" style="width: 120px; padding: 4px 8px; border: 1px solid var(--va-border); border-radius: 4px;">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Review Focus Areas</label>
                            <div class="va-tags-container">
                                <span class="va-tag selected">Performance Metrics</span>
                                <span class="va-tag selected">Value Creation</span>
                                <span class="va-tag">ESG Compliance</span>
                                <span class="va-tag">Exit Readiness</span>
                                <span class="va-tag">Risk Assessment</span>
                                <span class="va-tag">Operational KPIs</span>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Reporting Period</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Q4 2024</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="q4-2024">Q4 2024</div>
                                    <div class="va-dropdown-item" data-value="q3-2024">Q3 2024</div>
                                    <div class="va-dropdown-item" data-value="q2-2024">Q2 2024</div>
                                    <div class="va-dropdown-item" data-value="q1-2024">Q1 2024</div>
                                    <div class="va-dropdown-item" data-value="fy-2024">FY 2024</div>
                                    <div class="va-dropdown-item" data-value="fy-2023">FY 2023</div>
                                </div>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Generate Portfolio Review</button>
                    </div>
                </div>
            `;
    }

    // Additional CV artifacts
    createCVOptimizationArtifact(data) {
      return this.createCVTailoringArtifact(data); // Reuse CV tailoring interface
    }

    createCVEnhancementArtifact(data) {
      return this.createCVTailoringArtifact(data); // Reuse CV tailoring interface
    }

    createCVAnalysisArtifact(data) {
      return this.createCVTailoringArtifact(data); // Reuse CV tailoring interface
    }

    // Assessment & Evaluation artifacts
    createAssessmentArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Skills Assessment</h3>
                        <p>Evaluate your readiness for PE roles</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Current Role</label>
                            <input type="text" class="va-form-input" placeholder="e.g., Investment Banking Analyst">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Years of Experience</label>
                            <input type="number" class="va-form-input" min="0" max="30" placeholder="0">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Target PE Role</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Target Role</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="analyst">PE Analyst</div>
                                    <div class="va-dropdown-item" data-value="associate">PE Associate</div>
                                    <div class="va-dropdown-item" data-value="vp">Vice President</div>
                                    <div class="va-dropdown-item" data-value="principal">Principal</div>
                                    <div class="va-dropdown-item" data-value="partner">Partner</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Skills to Assess</label>
                            <div class="va-tags-container">
                                <span class="va-tag selected">Financial Modelling</span>
                                <span class="va-tag selected">Deal Execution</span>
                                <span class="va-tag">Portfolio Management</span>
                                <span class="va-tag">Fundraising</span>
                                <span class="va-tag">Sector Expertise</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Run Assessment</button>
                    </div>
                </div>
            `;
    }

    createEvaluationArtifact(data) {
      return this.createAssessmentArtifact(data); // Similar interface
    }

    // Planning artifacts
    createPlanningArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Career Planning Tool</h3>
                        <p>Create your PE career roadmap</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Planning Horizon</label>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
                                <button class="va-horizon active" data-horizon="6m" style="padding: 10px; background: var(--va-gradient); color: white; border: none; border-radius: 6px; cursor: pointer;">6 Months</button>
                                <button class="va-horizon" data-horizon="1y" style="padding: 10px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">1 Year</button>
                                <button class="va-horizon" data-horizon="2y" style="padding: 10px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">2 Years</button>
                                <button class="va-horizon" data-horizon="5y" style="padding: 10px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">5 Years</button>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Career Goal</label>
                            <input type="text" class="va-form-input" placeholder="e.g., Partner at mid-market PE fund">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Key Milestones</label>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="text" class="va-form-input" placeholder="Milestone 1">
                                <input type="text" class="va-form-input" placeholder="Milestone 2">
                                <input type="text" class="va-form-input" placeholder="Milestone 3">
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Create Roadmap</button>
                    </div>
                </div>
            `;
    }

    // Career guidance artifacts
    createCareerGuideArtifact(data) {
      return this.createPlanningArtifact(data); // Similar planning interface
    }

    createDevelopmentArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Skills Development Plan</h3>
                        <p>Build expertise for PE success</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Development Focus</label>
                            <div class="va-tags-container">
                                <span class="va-tag selected">Technical Skills</span>
                                <span class="va-tag">Leadership</span>
                                <span class="va-tag">Sector Knowledge</span>
                                <span class="va-tag">Deal Sourcing</span>
                                <span class="va-tag">LP Relations</span>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Current Proficiency</label>
                            <div class="va-slider-container">
                                <div class="va-slider-track">
                                    <div class="va-slider-fill" style="width: 40%;"></div>
                                    <div class="va-slider-handle" style="left: 40%;"></div>
                                </div>
                                <div class="va-slider-labels">
                                    <span class="va-slider-label">Beginner</span>
                                    <span class="va-slider-label">Intermediate</span>
                                    <span class="va-slider-label">Advanced</span>
                                    <span class="va-slider-label">Expert</span>
                                </div>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Generate Development Plan</button>
                    </div>
                </div>
            `;
    }

    // Calculator artifacts
    createCalculatorArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>PE Returns Calculator</h3>
                        <p>Calculate carry, IRR, and multiples</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Investment Amount</label>
                            <div class="va-currency-group">
                                <span class="va-currency-symbol">${
                                  this.currency === "EUR" ? "€" : "£"
                                }</span>
                                <input type="text" class="va-form-input va-currency-input" placeholder="0">
                                <div class="va-currency-toggle">
                                    <button class="va-currency-option ${
                                      this.currency === "GBP" ? "active" : ""
                                    }" data-currency="GBP">GBP</button>
                                    <button class="va-currency-option ${
                                      this.currency === "EUR" ? "active" : ""
                                    }" data-currency="EUR">EUR</button>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Target Multiple</label>
                            <input type="number" class="va-form-input" step="0.1" min="1" placeholder="2.5">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Hold Period (Years)</label>
                            <input type="number" class="va-form-input" min="1" max="10" placeholder="5">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Carry Percentage</label>
                            <input type="number" class="va-form-input" min="0" max="30" placeholder="20">
                        </div>

                        <button class="va-button-primary va-submit">Calculate Returns</button>
                    </div>
                </div>
            `;
    }

    // Content creation artifacts
    createBuilderArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Investment Thesis Builder</h3>
                        <p>Structure your investment case</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Company Name</label>
                            <input type="text" class="va-form-input" placeholder="Target Company">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Sector</label>
                            <input type="text" class="va-form-input" placeholder="e.g., B2B Software">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Investment Angle</label>
                            <div class="va-tags-container">
                                <span class="va-tag">Buy & Build</span>
                                <span class="va-tag">Operational Improvement</span>
                                <span class="va-tag">Digital Transformation</span>
                                <span class="va-tag">Market Consolidation</span>
                                <span class="va-tag">International Expansion</span>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Key Value Drivers</label>
                            <textarea class="va-form-input" rows="3" placeholder="List main value creation opportunities..."></textarea>
                        </div>

                        <button class="va-button-primary va-submit">Build Investment Thesis</button>
                    </div>
                </div>
            `;
    }

    createCreatorArtifact(data) {
      return this.createBuilderArtifact(data); // Similar creation interface
    }

    createGeneratorArtifact(data) {
      return this.createBuilderArtifact(data); // Similar generation interface
    }

    // Search artifacts
    createSearchArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Deal Search Parameters</h3>
                        <p>Find relevant investment opportunities</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Search Criteria</label>
                            <input type="text" class="va-form-input" placeholder="e.g., SaaS companies in UK">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Revenue Range</label>
                            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center;">
                                <div class="va-currency-group">
                                    <span class="va-currency-symbol">${
                                      this.currency === "EUR" ? "€" : "£"
                                    }</span>
                                    <input type="text" class="va-form-input va-currency-input" placeholder="Min">
                                </div>
                                <span>to</span>
                                <div class="va-currency-group">
                                    <span class="va-currency-symbol">${
                                      this.currency === "EUR" ? "€" : "£"
                                    }</span>
                                    <input type="text" class="va-form-input va-currency-input" placeholder="Max">
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Geography</label>
                            <div class="va-tags-container">
                                <span class="va-tag selected">UK</span>
                                <span class="va-tag">DACH</span>
                                <span class="va-tag">Nordics</span>
                                <span class="va-tag">Benelux</span>
                                <span class="va-tag">France</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Search Opportunities</button>
                    </div>
                </div>
            `;
    }

    createExplorationArtifact(data) {
      return this.createSearchArtifact(data); // Similar search interface
    }

    createFinderArtifact(data) {
      return this.createSearchArtifact(data); // Similar finder interface
    }

    // LinkedIn & Profile Artifacts
    createLinkedInAuditArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>LinkedIn Profile Audit</h3>
                        <p>Optimize your profile for PE recruiting</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">LinkedIn Profile URL</label>
                            <input type="text" class="va-form-input" id="va-linkedin-url" 
                                placeholder="linkedin.com/in/yourprofile">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Target PE Role</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Target Role</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="analyst">PE Analyst</div>
                                    <div class="va-dropdown-item" data-value="associate">PE Associate</div>
                                    <div class="va-dropdown-item" data-value="vp">Vice President</div>
                                    <div class="va-dropdown-item" data-value="principal">Principal</div>
                                    <div class="va-dropdown-item" data-value="partner">Partner</div>
                                    <div class="va-dropdown-item" data-value="operating">Operating Partner</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Audit Focus Areas</label>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" checked style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Headline & Summary</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" checked style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Experience Descriptions</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" checked style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Skills & Endorsements</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Keywords for PE Recruiters</span>
                                </label>
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" style="margin-right: 8px;">
                                    <span style="font-size: 14px;">Network Connections</span>
                                </label>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Geographic Focus</label>
                            <div class="va-tags-container">
                                <span class="va-tag selected">Dubai</span>
                                <span class="va-tag">Abu Dhabi</span>
                                <span class="va-tag">Riyadh</span>
                                <span class="va-tag">Cairo</span>
                                <span class="va-tag">Doha</span>
                                <span class="va-tag">Manama</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Analyze LinkedIn Profile</button>
                    </div>
                </div>
            `;
    }

    createExpertiseHighlightArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Industry Expertise Highlighter</h3>
                        <p>Emphasize your sector knowledge for PE</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Primary Sector Expertise</label>
                            <input type="text" class="va-form-input" placeholder="e.g., B2B SaaS, Healthcare, Fintech">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Years in Sector</label>
                            <input type="number" class="va-form-input" min="0" max="30" placeholder="0">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Key Achievements</label>
                            <textarea class="va-form-input" rows="3" placeholder="List your major sector achievements..."></textarea>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Expertise Areas to Highlight</label>
                            <div class="va-tags-container">
                                <span class="va-tag">M&A Experience</span>
                                <span class="va-tag">Operational Improvements</span>
                                <span class="va-tag">Market Analysis</span>
                                <span class="va-tag">Due Diligence</span>
                                <span class="va-tag">Portfolio Management</span>
                                <span class="va-tag">Value Creation</span>
                                <span class="va-tag">Exit Planning</span>
                                <span class="va-tag">Fundraising</span>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Highlight My Expertise</button>
                    </div>
                </div>
            `;
    }

    createStoryCraftingArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Leadership Story Crafter</h3>
                        <p>Build compelling STAR stories for interviews</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Story Type</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Story Type</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="leadership">Leadership Challenge</div>
                                    <div class="va-dropdown-item" data-value="deal">Deal Execution</div>
                                    <div class="va-dropdown-item" data-value="turnaround">Turnaround Success</div>
                                    <div class="va-dropdown-item" data-value="analysis">Complex Analysis</div>
                                    <div class="va-dropdown-item" data-value="teamwork">Team Collaboration</div>
                                    <div class="va-dropdown-item" data-value="innovation">Innovation/Initiative</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Situation (Context)</label>
                            <textarea class="va-form-input" rows="2" placeholder="Describe the situation or challenge..."></textarea>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Task (Your Role)</label>
                            <textarea class="va-form-input" rows="2" placeholder="What was your specific responsibility?"></textarea>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Action (What You Did)</label>
                            <textarea class="va-form-input" rows="2" placeholder="What specific actions did you take?"></textarea>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Result (Outcome)</label>
                            <textarea class="va-form-input" rows="2" placeholder="What was the quantifiable result?"></textarea>
                        </div>

                        <button class="va-button-primary va-submit">Craft My Story</button>
                    </div>
                </div>
            `;
    }

    createResponseArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Professional Response Builder</h3>
                        <p>Craft diplomatic professional responses</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Response Type</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Response Type</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="rejection">Job Rejection</div>
                                    <div class="va-dropdown-item" data-value="decline">Declining an Offer</div>
                                    <div class="va-dropdown-item" data-value="negotiate">Counter Offer</div>
                                    <div class="va-dropdown-item" data-value="followup">Interview Follow-up</div>
                                    <div class="va-dropdown-item" data-value="thank">Thank You Note</div>
                                    <div class="va-dropdown-item" data-value="withdrawal">Application Withdrawal</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Company/Contact Name</label>
                            <input type="text" class="va-form-input" placeholder="e.g., Blackstone, John Smith">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Role</label>
                            <input type="text" class="va-form-input" placeholder="e.g., PE Associate">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Key Points to Include</label>
                            <textarea class="va-form-input" rows="3" placeholder="Any specific points you want to mention..."></textarea>
                        </div>

                        <button class="va-button-primary va-submit">Generate Response</button>
                    </div>
                </div>
            `;
    }

    createRequestArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Referral Request Builder</h3>
                        <p>Request internal referrals effectively</p>
                    </div>
                    <div class="va-content">
                        <div class="va-form-group">
                            <label class="va-form-label">Connection Type</label>
                            <div class="va-dropdown">
                                <button class="va-dropdown-trigger">Select Connection Type</button>
                                <div class="va-dropdown-menu">
                                    <div class="va-dropdown-item" data-value="alumni">Alumni Network</div>
                                    <div class="va-dropdown-item" data-value="colleague">Former Colleague</div>
                                    <div class="va-dropdown-item" data-value="linkedin">LinkedIn Connection</div>
                                    <div class="va-dropdown-item" data-value="friend">Personal Friend</div>
                                    <div class="va-dropdown-item" data-value="mentor">Mentor/Advisor</div>
                                    <div class="va-dropdown-item" data-value="cold">Cold Outreach</div>
                                </div>
                            </div>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Target Firm</label>
                            <input type="text" class="va-form-input" placeholder="e.g., KKR, Carlyle, Apollo">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Target Role</label>
                            <input type="text" class="va-form-input" placeholder="e.g., Senior Associate">
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Your Connection/Value Add</label>
                            <textarea class="va-form-input" rows="3" placeholder="How you know them or what value you can bring..."></textarea>
                        </div>

                        <div class="va-form-group">
                            <label class="va-form-label">Tone</label>
                            <div style="display: flex; gap: 8px;">
                                <button class="va-tone-btn active" data-tone="formal" style="flex: 1; padding: 10px; background: var(--va-gradient); color: white; border: none; border-radius: 6px; cursor: pointer;">Formal</button>
                                <button class="va-tone-btn" data-tone="friendly" style="flex: 1; padding: 10px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">Friendly</button>
                                <button class="va-tone-btn" data-tone="casual" style="flex: 1; padding: 10px; background: var(--va-cream-light); border: 1px solid var(--va-border); border-radius: 6px; cursor: pointer;">Casual</button>
                            </div>
                        </div>

                        <button class="va-button-primary va-submit">Create Request Message</button>
                    </div>
                </div>
            `;
    }

    createDefaultArtifact(data) {
      return `
                <div class="visual-artifact-container">
                    <div class="va-header">
                        <button class="va-close"></button>
                        <h3>Action Parameters</h3>
                        <p>Configure your request</p>
                    </div>
                    <div class="va-content">
                        <p>Loading interface...</p>
                        <button class="va-button-primary va-submit">Continue</button>
                    </div>
                </div>
            `;
    }

    setCurrency(currency) {
      this.currency = currency;
      $(".va-currency-option").removeClass("active");
      $(`.va-currency-option[data-currency="${currency}"]`).addClass("active");
      $(".va-currency-symbol").text(currency === "EUR" ? "€" : "£");
    }

    handleSubmit() {
      // Collect form data based on artifact type
      const formData = this.collectFormData();

      // Build enhanced prompt
      const enhancedPrompt = this.buildEnhancedPrompt(formData);

      // Send to chat
      this.sendToChat(enhancedPrompt);

      // Close artifact
      this.closeArtifact();
    }

    collectFormData() {
      const data = {
        type: this.currentArtifact,
        currency: this.currency,
      };

      // Collect all form inputs
      $(".va-form-input").each(function () {
        const id = $(this).attr("id");
        if (id) {
          const key = id.replace("va-", "");
          data[key] = $(this).val();
        }
      });

      // Collect selected tags
      data.tags = [];
      $(".va-tag.selected").each(function () {
        data.tags.push($(this).text());
      });

      // Collect dropdown values
      $(".va-dropdown-trigger").each(function () {
        const value = $(this).data("value");
        if (value) {
          const dropdown = $(this).closest(".va-dropdown");
          const label = dropdown.prev(".va-form-label").text();
          data[label.toLowerCase().replace(/\s+/g, "_")] = value;
        }
      });

      return data;
    }

    buildEnhancedPrompt(data) {
      let prompt = "";

      switch (data.type) {
        case "cv-tailor":
          prompt = `Please tailor my CV for a ${
            data.target_role || "role"
          } position. `;
          if (data.company_type)
            prompt += `The company is in ${data.company_type}. `;
          if (data.tags.length)
            prompt += `Key skills to highlight: ${data.tags.join(", ")}. `;
          break;

        case "compensation":
          prompt = `Analyze compensation package: Base salary ${
            this.currency
          } ${data.base_salary || "0"}`;
          if (data.bonus_target)
            prompt += `, bonus target ${data.bonus_target}%`;
          if (data.location) prompt += `, location: ${data.location}`;
          if (data.experience)
            prompt += `, ${data.experience} years experience`;
          prompt += ". Provide market benchmarks and negotiation strategies.";
          break;

        case "market-research":
          prompt = `Conduct market research on ${
            data.research_scope || "the market"
          }. `;
          if (data.geographic_focus)
            prompt += `Geographic focus: ${data.geographic_focus}. `;
          if (data.tags.length) prompt += `Sectors: ${data.tags.join(", ")}. `;
          break;

        default:
          prompt = `Process ${data.type} with parameters: ${JSON.stringify(
            data
          )}`;
      }

      return prompt;
    }

    sendToChat(prompt) {
      // Integration with existing MENA Careers chat system
      const $input = $("#senna-input");
      if ($input.length) {
        $input.val(prompt);
        $("#senna-send, .sffc-send-btn").first().click();
      }
    }

    promptCVUpload() {
      // Close the current artifact
      this.closeArtifact();

      // Send message to chat
      this.sendToChat(
        "I'm ready to share my CV for tailored optimization. Please guide me through the upload process."
      );

      // Set flag for CV upload mode if action cards system exists
      if (window.actionCardsSystem && window.actionCardsSystem.userContext) {
        window.actionCardsSystem.userContext.awaitingCVUpload = true;
      }
    }
  }

  // Initialize the system
  $(document).ready(function () {
    window.visualArtifacts = new VisualArtifactsSystem();
  });
})(jQuery);
