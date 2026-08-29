/**
 * PE Filters JavaScript
 * Version: 1.0.0
 * Description: Private Equity specific filtering logic
 */

(function ($) {
  "use strict";

  class PEFilters {
    constructor() {
      this.activeFilters = this.loadFiltersFromStorage() || {
        seniority: null,
        fundSize: null,
        location: null,
        workStyle: null,
        geoFocus: null,
        fundType: [],
        salaryMin: 35,
        salaryMax: 500,
      };

      this.filterCounts = {
        total: 0,
        visible: 0,
      };

      this.filterSuggestions = {
        recentSearches: this.loadRecentSearches() || [],
        popularFilters: [],
        userPreferences: {},
      };

      this.filterMetadata = this.initializeFilterMetadata();

      this.isInitialized = false;
      this.debounceTimer = null;
      this.isApplyingFilters = false; // Flag to prevent infinite loops

      // Performance optimization: cache for filter results
      this.filterCache = new Map();
      this.cacheTimeout = 5000; // 5 seconds cache

      // Wait for DOM ready
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => this.init());
      } else {
        this.init();
      }
    }

    init() {
      console.log("PE Filters: Initializing...");

      // Listen for container ready event
      document.addEventListener("peFilterContainerReady", () => {
        console.log("PE Filters: Container ready event received");
        if (!this.isInitialized) {
          this.initializeFilters();
        }
      });

      // Also check if filter container already exists
      const checkContainer = setInterval(() => {
        const container = document.querySelector("#pe-filter-container");
        if (container && !this.isInitialized) {
          clearInterval(checkContainer);
          this.initializeFilters();
        }
      }, 500);

      // Stop checking after 10 seconds
      setTimeout(() => clearInterval(checkContainer), 10000);
    }

    initializeFilters() {
      this.isInitialized = true;
      console.log("PE Filters: Container found, loading filters...");

      // Load filter bar HTML
      this.loadFilterBar();

      // Listen for custom events that jobs have been loaded
      document.addEventListener("sennaJobsLoaded", () => {
        console.log(
          "PE Filters: Jobs loaded event received, updating counts..."
        );
        setTimeout(() => this.updateFilterCounts(), 100);
      });

      document.addEventListener("jobsRenderedInChat", () => {
        console.log("PE Filters: Jobs rendered in chat, updating counts...");
        setTimeout(() => this.updateFilterCounts(), 100);
      });

      // Initialize event listeners
      this.bindEvents();

      // Count initial jobs
      this.updateJobCount();
    }

    loadFilterBar() {
      const container = document.querySelector("#pe-filter-container");
      if (!container) {
        console.warn("PE Filters: Filter container not found, will retry...");
        return;
      }

      // Always use static HTML since we don't have server-side handler
      console.log("PE Filters: Loading static filter bar");
      this.insertStaticFilterBar(container);
    }

    insertStaticFilterBar(container) {
      try {
        // Transform container to vertical sidebar
        container.className = "pe-filter-sidebar";
        container.style = "";

        // Load prompt cards instead of traditional filters
        console.log("PE Filters: Loading prompt card system");
        this.loadPromptCards(container);
        return;
      } catch (error) {
        console.error("PE Filters: Error loading prompt cards:", error);
        this.loadTraditionalFilters(container);
      }
    }

    loadPromptCards(container) {
      // Check if cards system is already loaded or being loaded
      if (window.peFilterCardsSystem || window.PEFilterCardsSystem) {
        console.log(
          "PE Filters: Cards system already loaded, skipping duplicate load"
        );
        return;
      }

      // The script should already be loaded via WordPress enqueue
      // Just wait for it to be available
      let checkCount = 0;
      const checkForSystem = setInterval(() => {
        checkCount++;
        if (window.peFilterCardsSystem || window.PEFilterCardsSystem) {
          clearInterval(checkForSystem);
          console.log("PE Filters: Cards system detected, continuing...");
        } else if (checkCount > 20) {
          clearInterval(checkForSystem);
          console.log("PE Filters: Cards system not found after waiting");
        }
      }, 100);
    }

    loadFilterCardsScript(callback) {
      // This function is no longer needed - script is loaded via WordPress
      console.log("PE Filters: Script loading handled by WordPress enqueue");
      return;
      script.onload = () => {
        console.log("PE Filters: Extended cards script loaded successfully");
        callback();
      };
      script.onerror = () => {
        console.error("PE Filters: Failed to load prompt cards script");
        // Fall back to traditional filters
        this.loadTraditionalFilters(
          document.querySelector("#pe-filter-container") ||
            document.querySelector(".pe-filter-sidebar")
        );
      };
      document.head.appendChild(script);
    }

    initializePromptCards(container) {
      // Set up the basic structure
      container.innerHTML = `
                <!-- Stories Bar Style Quick Filters -->
                <div class="pe-quick-filters">
                    <div class="pe-quick-filter-item active" data-quick="all">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">ALL</div>
                        </div>
                        <span class="pe-filter-label">All</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="90plus">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">90%+</div>
                        </div>
                        <span class="pe-filter-label">Top Match</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="nearby">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">NRB</div>
                        </div>
                        <span class="pe-filter-label">Nearby</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="recent">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">NEW</div>
                        </div>
                        <span class="pe-filter-label">Recent</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="largecap">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">LC</div>
                        </div>
                        <span class="pe-filter-label">Large Cap</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="normal">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">WLB</div>
                        </div>
                        <span class="pe-filter-label">Work-Life</span>
                    </div>
                </div>
                
                <!-- Main Filters Container for Prompt Cards -->
                <div class="pe-main-filters">
                    <!-- Cards will be loaded here by the extended system -->
                </div>
            `;

      // Bind quick filter events
      this.bindQuickFilterEvents();

      // Listen for prompt filter events
      this.setupPromptFilterIntegration();

      // Initialize the extended card system
      if (window.PEFilterCardsSystem) {
        new window.PEFilterCardsSystem();
      } else if (window.peFilterCardsSystem) {
        // System already initialized
        console.log("PE Filters: Prompt cards system already active");
      }
    }

    bindQuickFilterEvents() {
      document.querySelectorAll(".pe-quick-filter-item").forEach((item) => {
        item.addEventListener("click", (e) => {
          // Remove active from all
          document
            .querySelectorAll(".pe-quick-filter-item")
            .forEach((i) => i.classList.remove("active"));
          // Add active to clicked
          item.classList.add("active");

          // Dispatch event for quick filter
          const filterType = item.dataset.quick;
          const event = new CustomEvent("quickFilterApplied", {
            detail: filterType,
          });
          document.dispatchEvent(event);
        });
      });
    }

    setupPromptFilterIntegration() {
      // Listen for prompt card filter applications
      document.addEventListener("promptFilterApplied", (e) => {
        const filters = e.detail;
        console.log("PE Filters: Received prompt filter:", filters);

        // Apply filters to our system
        Object.entries(filters).forEach(([key, value]) => {
          if (this.activeFilters[key] !== undefined) {
            this.activeFilters[key] = value;
          }
        });

        // Apply the filters
        this.applyFilters();

        // Update counts
        this.updateFilterCounts();
      });
    }

    loadTraditionalFilters(container) {
      console.log("PE Filters: Falling back to traditional filters");

      try {
        const filterHTML = `
                <!-- Stories Bar Style Quick Filters -->
                <div class="pe-quick-filters">
                    <div class="pe-quick-filter-item active" data-quick="all">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">ALL</div>
                        </div>
                        <span class="pe-filter-label">All</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="90plus">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">90%+</div>
                        </div>
                        <span class="pe-filter-label">Top Match</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="nearby">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">NRB</div>
                        </div>
                        <span class="pe-filter-label">Nearby</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="recent">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">NEW</div>
                        </div>
                        <span class="pe-filter-label">Recent</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="largecap">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">LC</div>
                        </div>
                        <span class="pe-filter-label">Large Cap</span>
                    </div>
                    <div class="pe-quick-filter-item" data-quick="normal">
                        <div class="pe-quick-icon">
                            <div class="pe-quick-icon-inner">WLB</div>
                        </div>
                        <span class="pe-filter-label">Work-Life</span>
                    </div>
                </div>
                
                <!-- Main Filters Container -->
                <div class="pe-main-filters">
                    <!-- Filter Header -->
                    <div class="pe-filter-header">
                        <h3>Filters</h3>
                        <button class="pe-clear-all">Clear All</button>
                    </div>

                    <!-- Seniority Section -->
                    <div class="pe-filter-section" data-filter="seniority">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Seniority
                        </h4>
                        <div class="pe-filter-options">
                            <label class="pe-filter-option" data-value="intern">
                                <input type="checkbox" name="seniority" value="intern">
                                <span class="pe-option-label">Early Career</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="analyst">
                                <input type="checkbox" name="seniority" value="analyst">
                                <span class="pe-option-label">Analyst</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="associate">
                                <input type="checkbox" name="seniority" value="associate">
                                <span class="pe-option-label">Associate</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="vp">
                                <input type="checkbox" name="seniority" value="vp">
                                <span class="pe-option-label">Vice President</span>
                                <span class="pe-option-count">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Fund Size Section -->
                    <div class="pe-filter-section" data-filter="fundSize">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="2" x2="12" y2="22"/>
                                <polyline points="17 7 12 2 7 7"/>
                                <polyline points="17 17 12 22 7 17"/>
                            </svg>
                            Fund Size
                        </h4>
                        <div class="pe-filter-options">
                            <label class="pe-filter-option" data-value="mega">
                                <input type="checkbox" name="fundSize" value="mega">
                                <span class="pe-option-label">Mega-Cap (€5bn+)</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="large">
                                <input type="checkbox" name="fundSize" value="large">
                                <span class="pe-option-label">Large-Cap (€1-5bn)</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="mid">
                                <input type="checkbox" name="fundSize" value="mid">
                                <span class="pe-option-label">Mid-Market</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="lower">
                                <input type="checkbox" name="fundSize" value="lower">
                                <span class="pe-option-label">Lower Mid</span>
                                <span class="pe-option-count">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Location Section -->
                    <div class="pe-filter-section" data-filter="location">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            Location
                        </h4>
                        <div class="pe-filter-options">
                            <label class="pe-filter-option" data-value="london">
                                <input type="checkbox" name="location" value="london">
                                <span class="pe-option-label">London</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="milan">
                                <input type="checkbox" name="location" value="milan">
                                <span class="pe-option-label">Milan</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="madrid">
                                <input type="checkbox" name="location" value="madrid">
                                <span class="pe-option-label">Madrid</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="global">
                                <input type="checkbox" name="location" value="global">
                                <span class="pe-option-label">Global PE</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="frankfurt">
                                <input type="checkbox" name="location" value="frankfurt">
                                <span class="pe-option-label">Frankfurt/Munich</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="paris">
                                <input type="checkbox" name="location" value="paris">
                                <span class="pe-option-label">Paris</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="saopaulo">
                                <input type="checkbox" name="location" value="saopaulo">
                                <span class="pe-option-label">São Paulo</span>
                                <span class="pe-option-count">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Work Style Section -->
                    <div class="pe-filter-section" data-filter="workStyle">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Work Style
                        </h4>
                        <div class="pe-filter-options">
                            <label class="pe-filter-option" data-value="normal">
                                <input type="checkbox" name="workStyle" value="normal">
                                <span class="pe-option-label">Normal Hours <span class="hint">(50-60h)</span></span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="fluctuates">
                                <input type="checkbox" name="workStyle" value="fluctuates">
                                <span class="pe-option-label">Fluctuates <span class="hint">(Deal-driven)</span></span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="intense">
                                <input type="checkbox" name="workStyle" value="intense">
                                <span class="pe-option-label">Intense <span class="hint">(70h+)</span></span>
                                <span class="pe-option-count">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Geographic Focus Section -->
                    <div class="pe-filter-section" data-filter="geoFocus">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Geo Focus
                        </h4>
                        <div class="pe-filter-options">
                            <label class="pe-filter-option" data-value="pan-european">
                                <input type="checkbox" name="geoFocus" value="pan-european">
                                <span class="pe-option-label">Pan-European</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="uk-ireland">
                                <input type="checkbox" name="geoFocus" value="uk-ireland">
                                <span class="pe-option-label">UK & Ireland</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="dach">
                                <input type="checkbox" name="geoFocus" value="dach">
                                <span class="pe-option-label">DACH Region</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="nordics">
                                <input type="checkbox" name="geoFocus" value="nordics">
                                <span class="pe-option-label">Nordics</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="global">
                                <input type="checkbox" name="geoFocus" value="global">
                                <span class="pe-option-label">Global</span>
                                <span class="pe-option-count">0</span>
                            </label>
                            <label class="pe-filter-option" data-value="emerging">
                                <input type="checkbox" name="geoFocus" value="emerging">
                                <span class="pe-option-label">Emerging EU</span>
                                <span class="pe-option-count">0</span>
                            </label>
                        </div>
                    </div>

                    <!-- Salary Range Section -->
                    <div class="pe-filter-section" data-filter="salary">
                        <h4 class="pe-section-title">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            Salary Range
                        </h4>
                        <div class="pe-filter-options">
                            <div class="pe-salary-slider">
                                <div class="pe-slider-track"></div>
                                <input type="range" min="100" max="500" value="100" class="pe-salary-min">
                                <input type="range" min="100" max="500" value="500" class="pe-salary-max">
                                <div class="pe-salary-labels">
                                    <span class="pe-salary-min-label">£35k</span>
                                    <span class="pe-salary-max-label">£500k+</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="pe-filter-actions">
                        <button class="pe-apply-filters">Apply Filters</button>
                        <button class="pe-save-search">Save Search</button>
                    </div>
                </div>
            `;

        container.innerHTML = filterHTML;
        this.bindFilterEvents();
      } catch (error) {
        console.error("PE Filters: Failed to load filter bar:", error);
        container.innerHTML = this.getErrorTemplate();
        throw error;
      }
    }

    getErrorTemplate() {
      return `
                <div class="pe-filter-bar" style="padding: 15px; background: #FFF3CD; color: #856404;">
                    <p style="margin: 0;">⚠️ Filters could not be loaded. Please refresh the page to try again.</p>
                </div>
            `;
    }

    bindEvents() {
      // Global events
      document.addEventListener("click", (e) => this.handleGlobalClick(e));

      // Watch for new job cards
      this.observeJobCards();
    }

    bindFilterEvents() {
      // Bind filter checkbox clicks in pe-main-filters
      document
        .querySelectorAll(
          '.pe-main-filters .pe-filter-option input[type="checkbox"]'
        )
        .forEach((checkbox) => {
          checkbox.addEventListener("change", (e) =>
            this.handleFilterCheckboxChange(e)
          );
        });

      // Bind quick filter clicks to send chat prompts
      document
        .querySelectorAll(".pe-quick-filter-item")
        .forEach((quickFilter) => {
          quickFilter.addEventListener("click", (e) =>
            this.handleQuickFilterClick(e)
          );
        });

      // Dropdown toggle
      document.querySelectorAll(".pe-filter-dropdown").forEach((dropdown) => {
        const button = dropdown.querySelector(".pe-filter-button");
        if (button) {
          button.addEventListener("click", (e) =>
            this.toggleDropdown(e, dropdown)
          );
        }

        // Dropdown item selection
        dropdown.querySelectorAll(".pe-dropdown-item").forEach((item) => {
          item.addEventListener("click", (e) =>
            this.selectFilterItem(e, dropdown, item)
          );
        });
      });

      // Quick filters
      document.querySelectorAll(".pe-quick-filter").forEach((filter) => {
        filter.addEventListener("click", (e) =>
          this.toggleQuickFilter(e, filter)
        );
      });

      // Clear filters - Updated to use pe-clear-all class
      const clearBtn = document.querySelector(
        ".pe-clear-all, .pe-clear-filters"
      );
      if (clearBtn) {
        clearBtn.addEventListener("click", () => {
          this.sendPromptToChat("Clear all filters and show all opportunities");
          this.clearAllFilters();
        });
      }

      // Compare button
      const compareBtn = document.querySelector("#pe-compare-btn");
      if (compareBtn) {
        compareBtn.addEventListener("click", () => this.handleCompareClick());
      }

      // Apply Filters button
      const applyBtn = document.querySelector(".pe-apply-filters");
      if (applyBtn) {
        applyBtn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          this.applyFilters();
        });
      }

      // Save Search button
      const saveBtn = document.querySelector(".pe-save-search");
      if (saveBtn) {
        saveBtn.addEventListener("click", () => {
          this.saveCurrentSearch();
        });
      }

      // Update counts with a delay to ensure jobs are loaded
      this.updateFilterCounts();

      // Try updating counts again after a delay in case jobs load asynchronously
      setTimeout(() => {
        this.updateFilterCounts();
      }, 1000);

      setTimeout(() => {
        this.updateFilterCounts();
      }, 2500);

      // Initialize salary slider
      this.initSalarySlider();
    }

    initSalarySlider() {
      const sliderContainer = document.querySelector(".pe-salary-slider");
      if (!sliderContainer) return;

      // Create slider input
      const sliderHTML = `
                <input type="range" 
                       class="pe-salary-range-input" 
                       min="35" 
                       max="500" 
                       value="35" 
                       step="5">
                <div class="pe-salary-track">
                    <div class="pe-salary-track-fill" style="width: 0%"></div>
                </div>
            `;

      sliderContainer.innerHTML = sliderHTML;

      const slider = sliderContainer.querySelector(".pe-salary-range-input");
      const trackFill = sliderContainer.querySelector(".pe-salary-track-fill");
      const minLabel = document.querySelector(".pe-salary-min-label");
      const maxLabel = document.querySelector(".pe-salary-max-label");

      // Initialize salary filter
      this.activeFilters.salaryMin = 35;
      this.activeFilters.salaryMax = 500;

      // Update on slider change
      slider.addEventListener("input", (e) => {
        const value = parseInt(e.target.value);
        const percentage = ((value - 35) / (500 - 35)) * 100;

        trackFill.style.width = percentage + "%";

        // Update labels
        minLabel.textContent = `£${value}k`;
        maxLabel.textContent = value >= 500 ? "£500k+" : `£${value + 50}k`;

        // Update active filters
        this.activeFilters.salaryMin = value;
        this.activeFilters.salaryMax = value >= 500 ? 500 : value + 50;
      });

      // Apply on change
      slider.addEventListener("change", () => {
        this.applyFilters();
      });
    }

    toggleDropdown(e, dropdown) {
      e.stopPropagation();

      // Close other dropdowns
      document.querySelectorAll(".pe-filter-dropdown").forEach((d) => {
        if (d !== dropdown) {
          d.classList.remove("open");
        }
      });

      // Toggle current
      const isOpening = !dropdown.classList.contains("open");
      dropdown.classList.toggle("open");

      // Position dropdown menu for fixed positioning
      if (isOpening) {
        const menu = dropdown.querySelector(".pe-dropdown-menu");
        const button = dropdown.querySelector(".pe-filter-button");
        if (menu && button) {
          const rect = button.getBoundingClientRect();
          menu.style.top = rect.bottom + 4 + "px";
          menu.style.left = rect.left + "px";
          menu.style.minWidth = Math.max(200, rect.width) + "px";
        }
      }
    }

    selectFilterItem(e, dropdown, item) {
      e.stopPropagation();

      const filterType = dropdown.dataset.filter;
      const value = item.dataset.value;
      const button = dropdown.querySelector(
        ".pe-filter-button span:first-child"
      );

      // Update selection
      dropdown.querySelectorAll(".pe-dropdown-item").forEach((i) => {
        i.classList.remove("selected");
      });
      item.classList.add("selected");

      // Update button text
      const itemText = item
        .querySelector("span:first-child")
        .textContent.split(" ")[0];
      button.textContent = itemText;

      // Mark button as active
      dropdown.querySelector(".pe-filter-button").classList.add("active");

      // Update active filters
      this.activeFilters[filterType] = value;

      // Close dropdown
      dropdown.classList.remove("open");

      // Apply filters
      this.applyFilters();
    }

    toggleQuickFilter(e, filter) {
      filter.classList.toggle("active");

      const fundType = filter.dataset.fundType;
      const index = this.activeFilters.fundType.indexOf(fundType);

      if (index > -1) {
        this.activeFilters.fundType.splice(index, 1);
      } else {
        this.activeFilters.fundType.push(fundType);
      }

      this.applyFilters();
    }

    showAllJobsInContext() {
      const messagesContainer = document.getElementById("senna-messages");
      if (messagesContainer) {
        // Remove filter opacity from pre-filter cards
        const preFilterCards = messagesContainer.querySelectorAll(
          ".job-cards-in-chat.pre-filter"
        );
        preFilterCards.forEach((container) => {
          container.classList.remove("pre-filter");
          container.style.opacity = "1";
        });
      }
    }

    getFilterSummary() {
      const parts = [];
      if (this.activeFilters.seniority)
        parts.push(this.activeFilters.seniority);
      if (this.activeFilters.fundSize)
        parts.push(`${this.activeFilters.fundSize}-cap`);
      if (this.activeFilters.location) parts.push(this.activeFilters.location);
      if (this.activeFilters.workStyle)
        parts.push(`${this.activeFilters.workStyle} hours`);
      if (this.activeFilters.geoFocus) parts.push(this.activeFilters.geoFocus);
      if (this.activeFilters.fundType.length > 0)
        parts.push(this.activeFilters.fundType.join(", "));
      return parts.join(", ") || "all filters";
    }

    handleCompareClick() {
      console.log("PE Filters: Compare button clicked");

      // Check if we have the job comparison instance
      if (window.peJobComparison && window.peJobComparison.selectedJobs) {
        const selectedJobs = window.peJobComparison.selectedJobs;

        if (selectedJobs.size > 0) {
          // Trigger comparison
          console.log("PE Filters: Comparing", selectedJobs.size, "jobs");
          window.peJobComparison.compareJobs();
        } else {
          console.warn("PE Filters: No jobs selected for comparison");
          alert("Please select at least 2 jobs to compare");
        }
      } else {
        console.error("PE Filters: Job comparison module not loaded");
        // Try to load it
        if (window.PEJobComparison) {
          window.peJobComparison = new window.PEJobComparison();
          setTimeout(() => this.handleCompareClick(), 500);
        } else {
          alert(
            "Job comparison feature is not available. Please refresh the page."
          );
        }
      }
    }

    clearAllFilters() {
      // Reset filter state
      this.activeFilters = {
        seniority: null,
        fundSize: null,
        location: null,
        workStyle: null,
        geoFocus: null,
        fundType: [],
        salaryMin: 35,
        salaryMax: 500,
      };

      // Reset UI
      document.querySelectorAll(".pe-filter-button").forEach((btn) => {
        btn.classList.remove("active");
        const dropdown = btn.closest(".pe-filter-dropdown");
        if (dropdown) {
          const filterType = dropdown.dataset.filter;
          const defaultText =
            filterType.charAt(0).toUpperCase() +
            filterType.slice(1).replace(/([A-Z])/g, " $1");
          btn.querySelector("span:first-child").textContent = defaultText;
        }
      });

      document.querySelectorAll(".pe-dropdown-item").forEach((item) => {
        item.classList.remove("selected");
      });

      document.querySelectorAll(".pe-quick-filter").forEach((filter) => {
        filter.classList.remove("active");
      });

      // Reset salary slider
      const slider = document.querySelector(".pe-salary-range-input");
      if (slider) {
        slider.value = 35;
        const trackFill = document.querySelector(".pe-salary-track-fill");
        if (trackFill) trackFill.style.width = "0%";
        const minLabel = document.querySelector(".pe-salary-min-label");
        const maxLabel = document.querySelector(".pe-salary-max-label");
        if (minLabel) minLabel.textContent = "£35k";
        if (maxLabel) maxLabel.textContent = "£500k+";
      }

      // Restore original job display in chat
      const chatController = window.sennaConversational;
      if (chatController && chatController.allJobs) {
        // Clear existing filtered display
        const messagesContainer = document.getElementById("senna-messages");
        if (messagesContainer) {
          const existingCards =
            messagesContainer.querySelectorAll(".job-cards-in-chat");
          existingCards.forEach((container) => container.remove());
        }

        // Show original jobs
        if (chatController.renderJobsInChat) {
          chatController.addSennaMessage(
            "Filters cleared. Showing all opportunities:"
          );
          chatController.renderJobsInChat(
            chatController.displayedJobs || chatController.allJobs.slice(0, 6)
          );
        }
      } else {
        // Fallback to showing all job cards
        this.showAllJobs();
      }
    }

    /**
     * Handle filter checkbox changes and send to chat
     */
    handleFilterCheckboxChange(e) {
      const checkbox = e.target;
      const filterOption = checkbox.closest(".pe-filter-option");
      const filterSection = checkbox.closest(".pe-filter-section");
      const filterType = filterSection.dataset.filter;
      const filterValue = filterOption.dataset.value;
      const filterLabel =
        filterOption.querySelector(".pe-option-label").textContent;

      // Send appropriate prompt to chat based on filter type
      let chatPrompt = "";

      if (checkbox.checked) {
        // Build search prompt based on filter type
        switch (filterType) {
          case "seniority":
            chatPrompt = `Show me ${filterLabel.toLowerCase()} level positions in private equity`;
            break;
          case "fundSize":
            chatPrompt = `Show me opportunities at ${filterLabel} private equity funds`;
            break;
          case "location":
            chatPrompt = `Show me private equity jobs in ${filterLabel}`;
            break;
          case "workStyle":
            const workStyleDesc = filterLabel
              .toLowerCase()
              .replace(/\s*\(.*?\)\s*/g, "");
            chatPrompt = `Show me PE opportunities with ${workStyleDesc} work hours`;
            break;
          case "geoFocus":
            chatPrompt = `Show me private equity positions with ${filterLabel} geographic focus`;
            break;
          default:
            chatPrompt = `Filter jobs by ${filterType}: ${filterLabel}`;
        }
      } else {
        // Remove filter
        chatPrompt = `Remove ${filterLabel} filter from the search`;
      }

      // Send prompt to MENA Careers chat
      this.sendPromptToChat(chatPrompt);

      // Update active filters
      // Check if this filter type should be an array or single value
      const arrayFilters = ["fundType"]; // Only fundType allows multiple selections

      if (arrayFilters.includes(filterType)) {
        // Handle as array (for multiple selections)
        if (!this.activeFilters[filterType]) {
          this.activeFilters[filterType] = [];
        }

        if (checkbox.checked) {
          if (!this.activeFilters[filterType].includes(filterValue)) {
            this.activeFilters[filterType].push(filterValue);
          }
        } else {
          const index = this.activeFilters[filterType].indexOf(filterValue);
          if (index > -1) {
            this.activeFilters[filterType].splice(index, 1);
          }
        }
      } else {
        // Handle as single value (radio-like behavior)
        if (checkbox.checked) {
          // Uncheck other checkboxes in the same filter section
          const filterSection = checkbox.closest(".pe-filter-section");
          filterSection
            .querySelectorAll('input[type="checkbox"]')
            .forEach((cb) => {
              if (cb !== checkbox) {
                cb.checked = false;
              }
            });
          // Set the single value
          this.activeFilters[filterType] = filterValue;
        } else {
          // Clear the filter
          this.activeFilters[filterType] = null;
        }
      }

      // Apply filters after a short delay
      setTimeout(() => this.applyFilters(), 300);
    }

    /**
     * Handle quick filter clicks
     */
    handleQuickFilterClick(e) {
      const quickFilter = e.currentTarget;
      const filterType =
        quickFilter.dataset.quick ||
        quickFilter
          .querySelector(".pe-filter-label")
          ?.textContent?.toLowerCase();
      const isActive = quickFilter.classList.contains("active");

      // Toggle active state
      document.querySelectorAll(".pe-quick-filter-item").forEach((item) => {
        item.classList.remove("active");
      });

      if (!isActive) {
        quickFilter.classList.add("active");

        // Apply filters based on quick filter type
        let filters = {};
        let chatPrompt = "";

        switch (filterType) {
          case "all":
          case "all jobs":
            // Clear all filters
            this.clearAllFilters();
            chatPrompt = "Show me all available private equity opportunities";
            break;
          case "90plus":
          case "top tier":
            filters = { fundSize: "mega" };
            this.activeFilters.fundSize = "mega";
            chatPrompt = "Show me opportunities at top-tier mega funds";
            break;
          case "nearby":
          case "london":
            filters = { location: "london" };
            this.activeFilters.location = "london";
            chatPrompt = "Show me private equity jobs in London";
            break;
          case "recent":
          case "new":
            // Sort by date (newest first)
            chatPrompt = "Show me the most recently posted PE opportunities";
            this.sortJobsByDate();
            break;
          case "largecap":
          case "high pay":
            filters = { salaryMin: 100 };
            this.activeFilters.salaryMin = 100;
            chatPrompt = "Show me high-paying PE opportunities (£100k+)";
            break;
          case "normal":
          case "remote":
            filters = { workStyle: "remote" };
            this.activeFilters.workStyle = "remote";
            chatPrompt = "Show me remote PE positions";
            break;
          case "growth":
            filters = { fundType: "growth" };
            this.activeFilters.fundType = ["growth"];
            chatPrompt = "Show me growth equity opportunities";
            break;
          default:
            chatPrompt = `Show me ${filterType} opportunities`;
        }

        // Apply the filters
        if (Object.keys(filters).length > 0) {
          this.applyFiltersWithFallback(filters, chatPrompt);
        } else {
          this.sendPromptToChat(chatPrompt);
          this.applyFilters();
        }
      } else {
        // Clear filters
        quickFilter.classList.remove("active");
        this.clearAllFilters();
        this.sendPromptToChat("Showing all opportunities");
      }
    }

    /**
     * Apply filters with intelligent fallback
     */
    applyFiltersWithFallback(filters, userQuery) {
      const chatController = window.sennaConversational;

      if (!chatController || !chatController.allJobs) {
        console.warn("Jobs not loaded yet");
        if (chatController) {
          chatController.addSennaMessage(
            "Please wait while I load available opportunities..."
          );
        }
        return;
      }

      // Apply filters and get results
      this.applyFilters();

      // Check if we have results
      const filteredJobs = this.lastFilteredJobs || [];

      if (filteredJobs.length === 0) {
        // No results - use the card system's intelligent fallback
        if (window.peFilterCardsSystem) {
          window.peFilterCardsSystem.provideNoResultsAdvice(filters, userQuery);
        } else {
          // Basic fallback
          chatController.addSennaMessage(
            `No exact matches found for "${userQuery}". Try adjusting your filters a bit or browse all available opportunities.`
          );
        }
      }
    }

    /**
     * Sort jobs by date (newest first)
     */
    sortJobsByDate() {
      const chatController = window.sennaConversational;
      if (chatController && chatController.allJobs) {
        // Sort jobs by date
        const sortedJobs = [...chatController.allJobs].sort((a, b) => {
          const dateA = new Date(a.posted_date || a.date || 0);
          const dateB = new Date(b.posted_date || b.date || 0);
          return dateB - dateA;
        });

        // Display sorted jobs
        chatController.addSennaMessage("Showing most recent opportunities:");
        setTimeout(() => {
          chatController.renderJobsInChat(sortedJobs.slice(0, 10), true);
        }, 300);
      }
    }

    /**
     * Send prompt to MENA Careers chat
     */
    sendPromptToChat(prompt) {
      console.log("PE Filters: Sending to chat:", prompt);

      // Check if MENA Careers conversational is available
      const chatController = window.sennaConversational;
      if (chatController) {
        // Add message as if user typed it
        if (chatController.addUserMessage) {
          chatController.addUserMessage(prompt);
        }

        // Process the filter request
        if (chatController.processMessage) {
          chatController.processMessage(prompt);
        } else if (chatController.handleUserMessage) {
          chatController.handleUserMessage(prompt);
        }
      } else {
        // Fallback: Try to find and trigger the chat input
        const chatInput = document.querySelector(
          '#senna-input, .senna-input, input[placeholder*="Type your message"]'
        );
        const sendButton = document.querySelector(
          '.senna-send-btn, button[type="submit"]'
        );

        if (chatInput && sendButton) {
          chatInput.value = prompt;
          chatInput.dispatchEvent(new Event("input", { bubbles: true }));

          // Trigger send
          setTimeout(() => {
            sendButton.click();
          }, 100);
        } else {
          console.warn(
            "PE Filters: Could not send prompt to chat - no chat interface found"
          );
          // Show message as toast fallback
          this.showToast(`Filter: ${prompt}`, "info");
        }
      }
    }

    /**
     * Performance optimized debounce utility
     */
    debounce(func, wait, immediate) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          if (!immediate) func.apply(this, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(this, args);
      };
    }

    applyFilters() {
      // Check cache first
      const cacheKey = this.getCacheKey();
      const cached = this.filterCache.get(cacheKey);

      if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
        console.log("PE Filters: Using cached results");
        this.renderCachedResults(cached.results);
        return;
      }

      // Debounce filter application for performance
      clearTimeout(this.debounceTimer);
      this.debounceTimer = setTimeout(() => {
        this.doApplyFilters();
        this.saveFiltersToStorage();
        this.saveRecentSearch();
      }, 300);
    }

    getCacheKey() {
      return JSON.stringify(this.activeFilters);
    }

    renderCachedResults(cachedJobs) {
      const chatController = window.sennaConversational;
      if (chatController && chatController.renderJobsInChat) {
        chatController.renderJobsInChat(cachedJobs, true);
      }
      this.updateMatchCount(cachedJobs.length);
    }

    doApplyFilters() {
      try {
        // Prevent re-entry if already applying filters
        if (this.isApplyingFilters) {
          console.log("PE Filters: Already applying filters, skipping...");
          return;
        }

        // Set flag to prevent infinite loop
        this.isApplyingFilters = true;

        // Get the chat controller instance
        const chatController = window.sennaConversational;
        if (!chatController || !chatController.allJobs) {
          console.warn("PE Filters: Chat controller not ready");
          this.showFilterError(
            "Job data is still loading. Please wait a moment."
          );
          this.isApplyingFilters = false;
          return;
        }

        // Validate job data
        if (
          !Array.isArray(chatController.allJobs) ||
          chatController.allJobs.length === 0
        ) {
          console.warn("PE Filters: No jobs available");
          this.showFilterError(
            " So sorry there are no jobs available to filter."
          );
          this.isApplyingFilters = false;
          return;
        }

        // Check if we have any active filters
        const hasFilters = this.hasActiveFilters();

        if (!hasFilters) {
          // No filters active - show all jobs
          this.showAllJobsInContext();
          this.isApplyingFilters = false;
          return;
        }

        // Show loading message
        if (chatController && chatController.addSennaMessage) {
          chatController.addSennaMessage("Let me see what I can find...");
        }

        // Filter the jobs that are already loaded in memory
        const allJobs = chatController.allJobs;
        const filteredJobs = allJobs.filter((job) => {
          return this.matchesFilters(job);
        });

        // Store filtered results
        this.lastFilteredJobs = filteredJobs;

        // Update the chat controller's filtered list
        if (chatController) {
          chatController.filteredJobs = filteredJobs;
        }

        // Cache the results for performance
        const cacheKey = this.getCacheKey();
        this.filterCache.set(cacheKey, {
          results: filteredJobs,
          timestamp: Date.now(),
        });

        // Clean old cache entries periodically
        if (this.filterCache.size > 50) {
          this.cleanCache();
        }

        // Display filtered results in chat
        if (filteredJobs.length > 0) {
          console.log(
            `PE Filters: Rendering ${filteredJobs.length} filtered jobs`
          );

          // Add filter results message
          if (chatController.addSennaMessage) {
            const filterSummary = this.getFilterSummary();
            chatController.addSennaMessage(
              ` Perfect! I Found ${filteredJobs.length} opportunities matching your filters (${filterSummary}):`
            );
          }

          // Small delay to ensure message is rendered first
          setTimeout(() => {
            // Render filtered jobs in chat
            if (chatController && chatController.renderJobsInChat) {
              console.log(
                "PE Filters: Calling renderJobsInChat with jobs:",
                filteredJobs
              );
              chatController.renderJobsInChat(filteredJobs, true);
            } else {
              console.log(
                "PE Filters: Using fallback render - controller missing"
              );
              // Fallback: manually insert jobs
              this.renderFilteredJobsInChat(filteredJobs);
            }
          }, 100);
        } else {
          // No matches message
          if (chatController.addSennaMessage) {
            chatController.addSennaMessage(
              " Ok it seems there is nothing matching what you searched for. Try changing it a bit, broaden the search."
            );
          }
        }

        // Update count
        this.updateMatchCount(filteredJobs.length);

        // Trigger event with filtered jobs
        document.dispatchEvent(
          new CustomEvent("peFiltersApplied", {
            detail: {
              filteredJobs: filteredJobs,
              visible: filteredJobs.length,
              total: chatController.allJobs.length,
            },
          })
        );

        // Reset flag after a delay to allow DOM updates
        setTimeout(() => {
          this.isApplyingFilters = false;
        }, 500);
      } catch (error) {
        console.error("PE Filters: Error applying filters:", error);
        this.isApplyingFilters = false;
        this.showFilterError(
          "An error occurred while filtering. Please try again."
        );
      }
    }

    showFilterError(message) {
      const chatController = window.sennaConversational;
      if (chatController && chatController.addSennaMessage) {
        chatController.addSennaMessage(`⚠️ ${message}`);
      } else {
        // Fallback: show temporary toast
        this.showToast(message, "error");
      }
    }

    showToast(message, type = "info") {
      const toast = document.createElement("div");
      toast.className = "pe-toast";
      toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === "error" ? "#DC3545" : "#17A2B8"};
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideInUp 0.3s ease;
            `;
      toast.textContent = message;

      document.body.appendChild(toast);

      setTimeout(() => {
        toast.style.animation = "slideOutDown 0.3s ease";
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    renderFilteredJobsInChat(jobs) {
      // Fallback method to render jobs if chat controller method not available
      const messagesContainer = document.getElementById("senna-messages");
      if (!messagesContainer) return;

      // Use the proper senna conversational createVogueJobCard if available
      const chatController = window.sennaConversational;

      let jobCardsHtml = "";
      if (chatController && chatController.createVogueJobCard) {
        // Use the proper Vogue card creation method
        const jobCards = jobs.map((job, index) => {
          return chatController.createVogueJobCard(job, index);
        });

        jobCardsHtml = `
                    <div class="job-cards-in-chat">
                        ${jobCards.join("")}
                    </div>
                `;
      } else {
        // Fallback to creating cards manually with proper structure
        const jobCards = jobs.map((job) => this.createJobCard(job));
        const jobsContainer = document.createElement("div");
        jobsContainer.className = "job-cards-in-chat";
        jobCards.forEach((card) => jobsContainer.appendChild(card));
        messagesContainer.appendChild(jobsContainer);
        return;
      }

      // Append to messages container
      const messageDiv = document.createElement("div");
      messageDiv.className = "message assistant";
      messageDiv.innerHTML = jobCardsHtml;
      messagesContainer.appendChild(messageDiv);

      // Store job data in the map for event handlers
      if (chatController && !chatController.jobDataMap) {
        chatController.jobDataMap = new Map();
      }

      if (chatController && jobs) {
        jobs.forEach((job) => {
          chatController.jobDataMap.set(job.id, job);
        });
      }

      // Bind events for the new cards
      if (chatController && chatController.bindCardEvents) {
        chatController.bindCardEvents();
      }
    }

    createJobCard(job) {
      const card = document.createElement("div");
      // Use proper classes: sffc-match-card job-card-vogue
      card.className = "sffc-match-card job-card-vogue chat-compact";
      card.dataset.jobId = job.id;

      // Add PE data attributes for easy access
      if (job.fund_size) card.dataset.fundSize = job.fund_size;
      if (job.work_style) card.dataset.workStyle = job.work_style;
      if (job.geo_focus) card.dataset.geoFocus = job.geo_focus;
      if (job.seniority_level)
        card.dataset.seniorityLevel = job.seniority_level;

      const matchScore = job.match_score || Math.floor(Math.random() * 20) + 75;
      const salaryDisplay =
        job.salary_display ||
        (job.salary_min && job.salary_max
          ? `$${Math.floor(job.salary_min / 1000)}k - $${Math.floor(
              job.salary_max / 1000
            )}k`
          : "Competitive");

      // Build highlights array
      const highlights = job.highlights || [];
      if (highlights.length === 0 && job.skills && job.skills.length > 0) {
        highlights.push(...job.skills.slice(0, 3));
      }

      card.innerHTML = `
                <div class="sffc-company-section">
                    <div class="sffc-company-logo">${
                      job.company ? job.company.charAt(0).toUpperCase() : "C"
                    }</div>
                    <h2 class="sffc-job-title">${job.title || "Position"}</h2>
                    <p class="sffc-company-name">${
                      job.company || "Company"
                    } • ${job.location || "Location"}</p>
                    
                    <div class="sffc-job-tags">
                        <span class="sffc-job-tag">${salaryDisplay}</span>
                        <span class="sffc-job-tag">${
                          job.job_type || "Full-time"
                        }</span>
                        ${
                          job.experience_level
                            ? `<span class="sffc-job-tag">${job.experience_level}</span>`
                            : ""
                        }
                    </div>
                    
                    ${
                      job.sffc_application_url || job.application_url
                        ? `
                    <div class="sffc-apply-button-container" style="margin-top: 16px;">
                        <button class="ask-senna-btn sffc-apply-btn" onclick="window.open('${
                          job.sffc_application_url || job.application_url
                        }', '_blank')" style="width: 100%; padding: 16px; background: #F5F2E8; border: 2px solid #F5F2E8; border-radius: 12px; color: #0d353e; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <span>Apply Now</span>
                            <span>→</span>
                        </button>
                    </div>
                    `
                        : ""
                    }
                </div>
                
                ${
                  highlights.length > 0
                    ? `
                <div class="sffc-match-highlights">
                    ${highlights
                      .slice(0, 3)
                      .map(
                        (highlight) => `
                        <div class="sffc-highlight">
                            <div class="sffc-highlight-icon">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                            </div>
                            <span>${highlight}</span>
                        </div>
                    `
                      )
                      .join("")}
                </div>
                `
                    : ""
                }
                
                <div class="sffc-match-actions">
                    <button class="sffc-btn-pass" data-job-id="${job.id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Pass</span>
                    </button>
                    <button class="sffc-btn-tailor" data-job-id="${job.id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        <span>Tailor CV</span>
                    </button>
                    <button class="sffc-btn-interested" data-job-id="${job.id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span>Interested</span>
                    </button>
                </div>
            `;

      return card;
    }

    extractJobData(card) {
      // Try to get job data from various sources
      let jobData = {};

      // Check for data-job attribute (primary source)
      const jobButton = card.querySelector("[data-job]");
      if (jobButton) {
        try {
          jobData = JSON.parse(jobButton.dataset.job.replace(/&apos;/g, "'"));
        } catch (e) {
          console.warn("Could not parse job data:", e);
        }
      }

      // Check for PE-specific data attributes
      if (card.dataset.fundSize) jobData.fund_size = card.dataset.fundSize;
      if (card.dataset.fundType) jobData.fund_type = card.dataset.fundType;
      if (card.dataset.workStyle) jobData.work_style = card.dataset.workStyle;
      if (card.dataset.seniorityLevel)
        jobData.seniority_level = card.dataset.seniorityLevel;
      if (card.dataset.geoFocus) jobData.geo_focus = card.dataset.geoFocus;

      // Fallback to extracting from DOM
      if (!jobData.title) {
        jobData.title = card.querySelector(".vogue-title")?.textContent || "";
      }
      if (!jobData.company) {
        jobData.company =
          card.querySelector(".vogue-company")?.textContent || "";
      }
      if (!jobData.location) {
        jobData.location =
          card.querySelector(".vogue-meta-item")?.textContent || "";
      }

      // Only enrich if PE data not already present
      if (!jobData.fund_size && !jobData.work_style) {
        jobData = this.enrichJobDataWithPEMetadata(jobData);
      }

      return jobData;
    }

    matchesFilters(jobData) {
      // Performance optimization: Early exit on first non-match
      // Check filters in order of likelihood to fail (most selective first)

      // Fund type is often most selective
      if (
        this.activeFilters.fundType.length > 0 &&
        !this.matchesFundType(jobData)
      ) {
        return false;
      }

      // Seniority is usually selective
      if (this.activeFilters.seniority && !this.matchesSeniority(jobData)) {
        return false;
      }

      // Fund size is moderately selective
      if (this.activeFilters.fundSize && !this.matchesFundSize(jobData)) {
        return false;
      }

      // Work style is less selective
      if (this.activeFilters.workStyle && !this.matchesWorkStyle(jobData)) {
        return false;
      }

      // Location filters
      if (this.activeFilters.location && !this.matchesLocation(jobData)) {
        return false;
      }

      if (this.activeFilters.geoFocus && !this.matchesGeoFocus(jobData)) {
        return false;
      }

      return true;
    }

    /**
     * Clean old cache entries to prevent memory leaks
     */
    cleanCache() {
      const now = Date.now();
      const entriesToDelete = [];

      // Find expired entries
      this.filterCache.forEach((value, key) => {
        if (now - value.timestamp > this.cacheTimeout) {
          entriesToDelete.push(key);
        }
      });

      // Delete expired entries
      entriesToDelete.forEach((key) => {
        this.filterCache.delete(key);
      });

      // If still too large, delete oldest entries
      if (this.filterCache.size > 30) {
        const sortedEntries = Array.from(this.filterCache.entries()).sort(
          (a, b) => a[1].timestamp - b[1].timestamp
        );

        // Keep only the 30 most recent
        const toKeep = sortedEntries.slice(-30);
        this.filterCache.clear();
        toKeep.forEach(([key, value]) => {
          this.filterCache.set(key, value);
        });
      }
    }

    matchesSeniority(jobData) {
      const filter = this.activeFilters.seniority;

      // First check if we have enriched seniority data
      if (jobData.seniority_level) {
        return jobData.seniority_level === filter;
      }

      // Fallback to title matching
      const title = (jobData.title || "").toLowerCase();
      const seniorityMap = {
        "vp-principal": ["vp", "vice president", "principal"],
        "director-md": ["director", "managing director", "md"],
        partner: ["partner"],
        operating: ["operating", "operational"],
      };

      const keywords = seniorityMap[filter] || [];
      return keywords.some((keyword) => title.includes(keyword));
    }

    matchesLocation(jobData) {
      const filter = this.activeFilters.location;

      // Use normalized location if available
      if (jobData.normalizedLocation) {
        return jobData.normalizedLocation === filter;
      }

      // Fallback to direct matching
      const location = (jobData.location || "").toLowerCase();
      const keywords = this.filterMetadata.locationMappings[filter] || [filter];

      return keywords.some((keyword) => location.includes(keyword));
    }

    matchesFundSize(jobData) {
      const filter = this.activeFilters.fundSize;

      // Use enriched metadata if available (note: field name is fund_size not fundSize)
      if (jobData.fund_size) {
        return jobData.fund_size === filter;
      }

      // Fallback to company name heuristics
      const company = (jobData.company || "").toLowerCase();
      const fundSizeMap = {
        mega: ["blackstone", "kkr", "apollo", "carlyle", "tpg"],
        large: ["permira", "cinven", "eqt", "cvc"],
        mid: ["bridgepoint", "montagu", "triton"],
        lower: ["ldc", "livingbridge"],
      };

      const keywords = fundSizeMap[filter] || [];
      return keywords.some((keyword) => company.includes(keyword));
    }

    matchesWorkStyle(jobData) {
      const filter = this.activeFilters.workStyle;

      // Use enriched metadata if available (note: field name is work_style)
      if (jobData.work_style) {
        return jobData.work_style === filter;
      }

      // Fallback heuristics based on company/title
      const company = (jobData.company || "").toLowerCase();
      const title = (jobData.title || "").toLowerCase();

      // Mega funds typically have intense hours
      if (filter === "intense") {
        const intenseFunds = [
          "blackstone",
          "kkr",
          "apollo",
          "carlyle",
          "goldman",
          "morgan stanley",
        ];
        return intenseFunds.some((fund) => company.includes(fund));
      }

      // Nordic/European funds often have better work-life balance
      if (filter === "normal") {
        const normalFunds = ["eqt", "nordic capital", "ldc", "livingbridge"];
        return normalFunds.some((fund) => company.includes(fund));
      }

      // Most others fluctuate
      return filter === "fluctuates";
    }

    matchesGeoFocus(jobData) {
      const filter = this.activeFilters.geoFocus;

      // Use enriched metadata if available (note: field name is geo_focus)
      if (jobData.geo_focus) {
        return jobData.geo_focus === filter;
      }

      // Fallback based on location and company
      const location = (jobData.location || "").toLowerCase();
      const company = (jobData.company || "").toLowerCase();

      const geoPatterns = {
        "pan-european": ["european", "europe", "eu", "pan-european"],
        "uk-ireland": ["uk", "london", "manchester", "dublin", "ireland"],
        dach: [
          "germany",
          "austria",
          "switzerland",
          "frankfurt",
          "munich",
          "zurich",
        ],
        nordics: [
          "sweden",
          "norway",
          "denmark",
          "finland",
          "stockholm",
          "copenhagen",
        ],
        global: ["global", "international", "worldwide"],
        emerging: ["poland", "czech", "hungary", "romania", "turkey"],
      };

      const patterns = geoPatterns[filter] || [];
      return patterns.some(
        (pattern) => location.includes(pattern) || company.includes(pattern)
      );
    }

    matchesFundType(jobData) {
      // First check if we have enriched fund_type data
      if (jobData.fund_type) {
        return this.activeFilters.fundType.includes(jobData.fund_type);
      }

      // Fallback to company name matching
      const company = (jobData.company || "").toLowerCase();

      for (let type of this.activeFilters.fundType) {
        if (type === "top-tier") {
          // Major PE firms
          const topTier = [
            "blackstone",
            "kkr",
            "apollo",
            "carlyle",
            "tpg",
            "warburg",
            "cvc",
            "advent",
          ];
          if (topTier.some((t) => company.includes(t))) return true;
        }
        if (type === "asset-manager") {
          // Asset management firms
          const assetManagers = [
            "blackrock",
            "vanguard",
            "fidelity",
            "schroders",
            "pimco",
            "wellington",
            "state street",
            "invesco",
            "amundi",
          ];
          if (assetManagers.some((t) => company.includes(t))) return true;
        }
        if (type === "alternative-asset") {
          // Alternative asset managers
          const altAsset = [
            "ares",
            "oaktree",
            "brookfield",
            "fortress",
            "cerberus",
          ];
          if (altAsset.some((t) => company.includes(t))) return true;
        }
        if (type === "mid-size") {
          // Mid-size PE firms
          const midSize = [
            "permira",
            "cinven",
            "eqt",
            "bridgepoint",
            "montagu",
            "triton",
          ];
          if (midSize.some((t) => company.includes(t))) return true;
        }
        if (type === "boutique") {
          // Smaller/boutique funds
          const boutique = ["ldc", "livingbridge", "growth capital"];
          if (boutique.some((t) => company.includes(t))) return true;
        }
      }

      return false;
    }

    showAllJobs() {
      // Just show all existing job cards without filtering
      const jobCards = document.querySelectorAll(".job-card-vogue");
      jobCards.forEach((card) => {
        card.style.display = "";
        card.classList.remove("filtered-out");
      });
      this.updateJobCount();
    }

    updateMatchCount(count) {
      const countElement = document.querySelector(".pe-match-count");
      if (countElement) {
        countElement.textContent = count;
      }
    }

    updateJobCount() {
      try {
        // Target job cards in the chat container specifically
        const chatContainer = document.querySelector(".job-cards-in-chat");
        const selector = chatContainer
          ? ".job-cards-in-chat .job-card-vogue"
          : ".job-card-vogue";

        const total = document.querySelectorAll(selector).length;
        const visible = document.querySelectorAll(
          selector + ":not(.filtered-out)"
        ).length;

        this.filterCounts.total = total;
        this.filterCounts.visible = visible;

        this.updateMatchCount(visible);

        // Log if no jobs found (not an error, just info)
        if (total === 0) {
          console.info("PE Filters: No job cards found on page yet");
        }
      } catch (error) {
        console.error("PE Filters: Error updating job count:", error);
        this.filterCounts.total = 0;
        this.filterCounts.visible = 0;
      }
    }

    updateFilterCounts() {
      // Get actual jobs from the page or from sennaConversational
      const chatController = window.sennaConversational;
      let jobs = chatController?.allJobs || [];

      // If no jobs loaded yet, try to get from DOM
      if (jobs.length === 0) {
        const jobCards = document.querySelectorAll(
          ".job-card-vogue, .sffc-match-card"
        );
        jobCards.forEach((card) => {
          try {
            const dataJob = card.getAttribute("data-job");
            if (dataJob) {
              const jobData = JSON.parse(dataJob);
              jobs.push(jobData);
            } else {
              // Try to extract basic job data from the card
              const title =
                card.querySelector(".sffc-job-title, h3")?.textContent || "";
              const company =
                card.querySelector(".sffc-company-name, .company")
                  ?.textContent || "";
              const location =
                card.querySelector(".sffc-location, .location")?.textContent ||
                "";
              if (title) {
                jobs.push({ title, company, location });
              }
            }
          } catch (e) {
            // Skip invalid data
          }
        });
      }

      // Calculate actual counts based on real job data
      const counts = {
        seniority: {},
        fundSize: {},
        location: {},
        workStyle: {},
        geoFocus: {},
      };

      // Initialize all counts to 0 - Updated with correct filter values
      const filterOptions = {
        seniority: ["intern", "analyst", "associate", "vp"], // Updated to match our UI
        fundSize: ["mega", "large", "mid", "lower"],
        location: [
          "london",
          "milan",
          "madrid",
          "global",
          "frankfurt",
          "paris",
          "saopaulo",
        ], // Updated to match our UI
        workStyle: ["normal", "fluctuates", "intense"],
        geoFocus: [
          "pan-european",
          "uk-ireland",
          "dach",
          "nordics",
          "global",
          "emerging",
        ],
      };

      Object.keys(filterOptions).forEach((filterType) => {
        filterOptions[filterType].forEach((option) => {
          counts[filterType][option] = 0;
        });
      });

      // Count jobs for each filter
      jobs.forEach((job) => {
        // Seniority - based on title (updated for our new options)
        const title = (job.title || "").toLowerCase();

        // Check for Intern level
        if (
          title.includes("intern") ||
          title.includes("trainee") ||
          title.includes("placement")
        ) {
          counts.seniority["intern"]++;
        }
        // Check for Analyst level
        else if (title.includes("analyst") || title.includes("junior")) {
          counts.seniority["analyst"]++;
        }
        // Check for Associate level
        else if (
          title.includes("associate") ||
          title.includes("senior analyst")
        ) {
          counts.seniority["associate"]++;
        }
        // Check for VP level
        else if (
          title.includes("vp") ||
          title.includes("vice president") ||
          title.includes("principal") ||
          title.includes("director") ||
          title.includes("managing director") ||
          title.includes("partner")
        ) {
          counts.seniority["vp"]++;
        }
        // Default to Analyst if unclear
        else {
          counts.seniority["analyst"]++;
        }

        // Fund Size - based on company (simplified matching)
        const company = (job.company || "").toLowerCase();
        if (
          company.includes("kkr") ||
          company.includes("blackstone") ||
          company.includes("carlyle") ||
          company.includes("apollo")
        ) {
          counts.fundSize["mega"]++;
        } else if (
          company.includes("advent") ||
          company.includes("cinven") ||
          company.includes("permira")
        ) {
          counts.fundSize["large"]++;
        } else if (company.includes("bridgepoint") || company.includes("eqt")) {
          counts.fundSize["mid"]++;
        } else {
          counts.fundSize["lower"]++;
        }

        // Location - based on location field (updated for our new locations)
        const location = (job.location || "").toLowerCase();
        if (location.includes("london")) counts.location["london"]++;
        if (location.includes("milan") || location.includes("milano"))
          counts.location["milan"]++;
        if (location.includes("madrid")) counts.location["madrid"]++;
        if (
          location.includes("global") ||
          location.includes("private equity") ||
          location.includes("buyout") ||
          location.includes("growth equity")
        )
          counts.location["global"]++;
        if (
          location.includes("frankfurt") ||
          location.includes("munich") ||
          location.includes("münchen")
        )
          counts.location["frankfurt"]++;
        if (location.includes("paris")) counts.location["paris"]++;
        if (
          location.includes("são paulo") ||
          location.includes("sao paulo") ||
          location.includes("brazil")
        )
          counts.location["saopaulo"]++;

        // Default to London if no specific location
        if (!location || location === "") {
          counts.location["london"]++;
        }

        // Work Style - estimate based on company type
        if (company.includes("operating") || title.includes("operating")) {
          counts.workStyle["normal"]++;
        } else if (company.includes("growth") || company.includes("venture")) {
          counts.workStyle["fluctuates"]++;
        } else {
          counts.workStyle["intense"]++;
        }

        // Geo Focus - based on location
        if (location.includes("london") || location.includes("dublin")) {
          counts.geoFocus["uk-ireland"]++;
        } else if (
          location.includes("frankfurt") ||
          location.includes("munich") ||
          location.includes("zurich")
        ) {
          counts.geoFocus["dach"]++;
        } else if (
          location.includes("stockholm") ||
          location.includes("copenhagen")
        ) {
          counts.geoFocus["nordics"]++;
        } else if (
          location.includes("new york") ||
          location.includes("singapore") ||
          location.includes("hong kong")
        ) {
          counts.geoFocus["global"]++;
        } else {
          counts.geoFocus["pan-european"]++;
        }
      });

      // Update counts in UI - Fixed to use correct class selector
      Object.keys(counts).forEach((filterType) => {
        // For sidebar filter sections
        const section = document.querySelector(
          `.pe-filter-section[data-filter="${filterType}"]`
        );
        if (section) {
          Object.keys(counts[filterType]).forEach((value) => {
            const option = section.querySelector(
              `.pe-filter-option[data-value="${value}"] .pe-option-count`
            );
            if (option) {
              option.textContent = counts[filterType][value];
            }
          });
        }

        // Also update dropdown if present
        const dropdown = document.querySelector(
          `.pe-filter-dropdown[data-filter="${filterType}"]`
        );
        if (dropdown) {
          Object.keys(counts[filterType]).forEach((value) => {
            const item = dropdown.querySelector(
              `[data-value="${value}"] .count`
            );
            if (item) {
              item.textContent = counts[filterType][value];
            }
          });
        }
      });

      console.log(
        "PE Filters: Updated counts for",
        jobs.length,
        "jobs",
        counts
      );
    }

    observeJobCards() {
      // Watch for new job cards being added
      const observer = new MutationObserver((mutations) => {
        let hasNewCards = false;

        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1) {
              // Element node
              if (node.classList && node.classList.contains("job-card-vogue")) {
                hasNewCards = true;
              }
              if (
                node.classList &&
                node.classList.contains("job-cards-in-chat")
              ) {
                hasNewCards = true;
              }
              if (node.querySelectorAll) {
                const cards = node.querySelectorAll(".job-card-vogue");
                if (cards.length > 0) hasNewCards = true;
              }
            }
          });
        });

        if (hasNewCards) {
          // Small delay to ensure DOM is fully updated
          setTimeout(() => {
            this.updateJobCount();
            this.updateFilterCounts(); // Recalculate filter counts when new jobs appear
            // Don't automatically re-apply filters when jobs are rendered
            // This prevents duplicate filtering when intelligent search is running
            /* Commented out to prevent duplicate filtering
                        if (this.hasActiveFilters() && !this.isApplyingFilters) {
                            this.applyFilters();
                        }
                        */
          }, 100);
        }
      });

      // Observe multiple potential containers
      const containers = [
        ".sffc-messages-container",
        ".job-cards-in-chat",
        ".sffc-opportunities-wrapper",
      ];

      containers.forEach((selector) => {
        const container = document.querySelector(selector);
        if (container) {
          observer.observe(container, {
            childList: true,
            subtree: true,
          });
        }
      });
    }

    hasActiveFilters() {
      return (
        this.activeFilters.seniority ||
        this.activeFilters.fundSize ||
        this.activeFilters.location ||
        this.activeFilters.workStyle ||
        this.activeFilters.geoFocus ||
        this.activeFilters.fundType.length > 0
      );
    }

    handleGlobalClick(e) {
      // Close dropdowns when clicking outside
      if (!e.target.closest(".pe-filter-dropdown")) {
        document.querySelectorAll(".pe-filter-dropdown").forEach((dropdown) => {
          dropdown.classList.remove("open");
        });
      }
    }

    // ========================================
    // PHASE 3: Enhanced Filter Logic
    // ========================================

    initializeFilterMetadata() {
      return {
        fundProfiles: {
          blackstone: {
            size: "mega",
            type: "top-tier",
            geoFocus: "global",
            workStyle: "intense",
          },
          kkr: {
            size: "mega",
            type: "top-tier",
            geoFocus: "global",
            workStyle: "intense",
          },
          apollo: {
            size: "mega",
            type: "top-tier",
            geoFocus: "global",
            workStyle: "intense",
          },
          carlyle: {
            size: "mega",
            type: "top-tier",
            geoFocus: "global",
            workStyle: "intense",
          },
          tpg: {
            size: "mega",
            type: "top-tier",
            geoFocus: "global",
            workStyle: "intense",
          },
          permira: {
            size: "large",
            type: "top-tier",
            geoFocus: "pan-european",
            workStyle: "fluctuates",
          },
          cinven: {
            size: "large",
            type: "mid-size",
            geoFocus: "pan-european",
            workStyle: "fluctuates",
          },
          eqt: {
            size: "large",
            type: "mid-size",
            geoFocus: "nordics",
            workStyle: "normal",
          },
          cvc: {
            size: "large",
            type: "top-tier",
            geoFocus: "pan-european",
            workStyle: "intense",
          },
          bridgepoint: {
            size: "mid",
            type: "mid-size",
            geoFocus: "pan-european",
            workStyle: "fluctuates",
          },
          montagu: {
            size: "mid",
            type: "mid-size",
            geoFocus: "uk-ireland",
            workStyle: "normal",
          },
          triton: {
            size: "mid",
            type: "mid-size",
            geoFocus: "dach",
            workStyle: "fluctuates",
          },
          ldc: {
            size: "lower",
            type: "boutique",
            geoFocus: "uk-ireland",
            workStyle: "normal",
          },
          livingbridge: {
            size: "lower",
            type: "boutique",
            geoFocus: "uk-ireland",
            workStyle: "normal",
          },
        },
        locationMappings: {
          london: ["uk", "united kingdom", "england"],
          global: ["global", "private equity", "buyout", "growth equity"],
          paris: ["france", "île-de-france"],
          frankfurt: ["germany", "deutschland", "munich", "münchen", "berlin"],
          nordic: [
            "stockholm",
            "copenhagen",
            "oslo",
            "helsinki",
            "sweden",
            "denmark",
            "norway",
            "finland",
          ],
          amsterdam: ["netherlands", "holland", "rotterdam", "the hague"],
        },
        seniorityKeywords: {
          "vp-principal": [
            "vp",
            "vice president",
            "principal",
            "senior associate",
          ],
          "director-md": [
            "director",
            "managing director",
            "md",
            "partner-track",
          ],
          partner: ["partner", "general partner", "gp", "managing partner"],
          operating: [
            "operating partner",
            "operational partner",
            "portfolio operations",
          ],
        },
      };
    }

    enrichJobDataWithPEMetadata(jobData) {
      const company = (jobData.company || "").toLowerCase();

      // Check if we have metadata for this company
      for (const [fundName, metadata] of Object.entries(
        this.filterMetadata.fundProfiles
      )) {
        if (company.includes(fundName)) {
          jobData.fundSize = metadata.size;
          jobData.fundType = metadata.type;
          jobData.geoFocus = metadata.geoFocus;
          jobData.workStyle = metadata.workStyle;
          break;
        }
      }

      // Extract seniority from title
      const title = (jobData.title || "").toLowerCase();
      for (const [level, keywords] of Object.entries(
        this.filterMetadata.seniorityKeywords
      )) {
        if (keywords.some((keyword) => title.includes(keyword))) {
          jobData.seniority = level;
          break;
        }
      }

      // Normalize location
      const location = (jobData.location || "").toLowerCase();
      for (const [key, variations] of Object.entries(
        this.filterMetadata.locationMappings
      )) {
        if (variations.some((variant) => location.includes(variant))) {
          jobData.normalizedLocation = key;
          break;
        }
      }

      return jobData;
    }

    // Filter Persistence
    saveFiltersToStorage() {
      try {
        localStorage.setItem(
          "pe_active_filters",
          JSON.stringify(this.activeFilters)
        );
        this.trackFilterUsage();
      } catch (e) {
        console.warn("Could not save filters to storage:", e);
      }
    }

    loadFiltersFromStorage() {
      try {
        const stored = localStorage.getItem("pe_active_filters");
        return stored ? JSON.parse(stored) : null;
      } catch (e) {
        console.warn("Could not load filters from storage:", e);
        return null;
      }
    }

    // Recent Searches & Suggestions
    saveRecentSearch() {
      const searchSnapshot = {
        filters: { ...this.activeFilters },
        timestamp: Date.now(),
        resultCount: this.filterCounts.visible,
      };

      let recentSearches = this.loadRecentSearches() || [];
      recentSearches.unshift(searchSnapshot);
      recentSearches = recentSearches.slice(0, 10); // Keep last 10

      try {
        localStorage.setItem(
          "pe_recent_searches",
          JSON.stringify(recentSearches)
        );
      } catch (e) {
        console.warn("Could not save recent searches:", e);
      }
    }

    loadRecentSearches() {
      try {
        const stored = localStorage.getItem("pe_recent_searches");
        return stored ? JSON.parse(stored) : [];
      } catch (e) {
        return [];
      }
    }

    // Analytics & Tracking
    trackFilterUsage() {
      const usage = {
        filters: this.activeFilters,
        timestamp: Date.now(),
        resultCount: this.filterCounts.visible,
        sessionId: this.getSessionId(),
      };

      // Send to analytics if available
      if (typeof gtag !== "undefined") {
        gtag("event", "pe_filter_applied", {
          event_category: "PE_Filters",
          event_label: JSON.stringify(this.activeFilters),
          value: this.filterCounts.visible,
        });
      }

      // Also track locally for suggestions
      this.updateFilterPopularity();
    }

    updateFilterPopularity() {
      try {
        let popularity = JSON.parse(
          localStorage.getItem("pe_filter_popularity") || "{}"
        );

        Object.keys(this.activeFilters).forEach((filterType) => {
          const value = this.activeFilters[filterType];
          if (
            value &&
            value !== null &&
            (!Array.isArray(value) || value.length > 0)
          ) {
            const key = `${filterType}:${value}`;
            popularity[key] = (popularity[key] || 0) + 1;
          }
        });

        localStorage.setItem(
          "pe_filter_popularity",
          JSON.stringify(popularity)
        );
      } catch (e) {
        console.warn("Could not update filter popularity:", e);
      }
    }

    getSessionId() {
      let sessionId = sessionStorage.getItem("pe_session_id");
      if (!sessionId) {
        sessionId =
          "sess_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem("pe_session_id", sessionId);
      }
      return sessionId;
    }

    // Smart Suggestions
    getSuggestedFilters() {
      const suggestions = [];

      // Based on current partial selection
      if (this.activeFilters.location && !this.activeFilters.workStyle) {
        suggestions.push({
          type: "workStyle",
          value: "fluctuates",
          reason: "Most common for " + this.activeFilters.location,
        });
      }

      if (
        this.activeFilters.fundSize === "mega" &&
        !this.activeFilters.workStyle
      ) {
        suggestions.push({
          type: "workStyle",
          value: "intense",
          reason: "Typical for mega-cap funds",
        });
      }

      return suggestions;
    }

    /**
     * Save current search configuration
     */
    saveCurrentSearch() {
      try {
        const searchName = prompt("Enter a name for this saved search:");
        if (!searchName) return;

        const savedSearches = JSON.parse(
          localStorage.getItem("pe_saved_searches") || "[]"
        );
        const newSearch = {
          id: Date.now(),
          name: searchName,
          filters: { ...this.activeFilters },
          created: new Date().toISOString(),
          count: this.lastFilteredJobs ? this.lastFilteredJobs.length : 0,
        };

        savedSearches.push(newSearch);

        // Keep only last 10 saved searches
        if (savedSearches.length > 10) {
          savedSearches.shift();
        }

        localStorage.setItem(
          "pe_saved_searches",
          JSON.stringify(savedSearches)
        );

        // Show success message
        if (
          window.sennaConversational &&
          window.sennaConversational.addSennaMessage
        ) {
          window.sennaConversational.addSennaMessage(
            `✅ Search "${searchName}" has been saved successfully!`
          );
        } else {
          alert(`Search "${searchName}" has been saved successfully!`);
        }

        console.log("PE Filters: Search saved:", newSearch);
      } catch (error) {
        console.error("PE Filters: Error saving search:", error);
        alert("Failed to save search. Please try again.");
      }
    }
  }

  // Initialize when ready
  window.PEFilters = PEFilters;

  // Initialize PE Filters when document is ready
  $(document).ready(() => {
    // Always initialize PE filters if the AJAX config exists
    if (!window.peFilters && window.peFiltersAjax) {
      window.peFilters = new PEFilters();
    }
  });

  // Also initialize immediately if jQuery is ready
  if (window.jQuery && !window.peFilters) {
    window.peFilters = new PEFilters();
  }
})(jQuery);
