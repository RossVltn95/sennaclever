/**
 * Private Equity Search Interface
 * Google-style search functionality with instant Fuse.js autosuggest
 */

(function ($) {
  "use strict";

  class PESearchInterface {
    constructor(container) {
      this.container = $(container);
      this.isResultsPageContext =
        this.container.closest(".sffc-pe-results-container").length > 0 ||
        $(".sffc-pe-results-container").length > 0;

      this.searchInput = this.container.find(".sffc-search-input");
      this.modeButtons = this.container.find(
        ".sffc-mode-tab, .sffc-mode-tab-compact"
      );
      this.autocompleteDropdown = this.container.find(
        ".sffc-autocomplete-dropdown"
      );
      this.autocompleteContent = this.container.find(
        ".sffc-autocomplete-content"
      );
      this.searchButtons = this.container.find(
        ".sffc-search-btn, .sffc-search-btn-compact"
      );
      this.clearButton = this.container.find(".sffc-search-clear");
      this.voiceButton = this.container.find(".sffc-voice-search");

      this.currentMode = this.normalizeMode(
        this.searchInput.data("mode") || "jobs"
      );
      this.searchInput.data("mode", this.currentMode);

      this.searchTimeout = null;
      this.isSearching = false;
      this.selectedSuggestionIndex = -1;
      this.suggestions = [];
      this.localCache = null;
      this.fuseInstances = {};

      this.init();
    }

    init() {
      if (this.isResultsPageContext) return;

      const urlQuery = new URLSearchParams(window.location.search).get("q");
      if (urlQuery && urlQuery !== this.searchInput.val()) {
        this.searchInput.val(urlQuery);
      }

      this.bindEvents();
      this.setupVoiceSearch();
      this.loadTrendingQueries();
      this.syncActiveModeButtons();
      this.preloadSuggestions();
    }

    bindEvents() {
      this.modeButtons.on("click", (e) => this.switchMode($(e.currentTarget)));
      this.searchInput.on("input", (e) => this.handleSearchInput(e));
      this.searchInput.on("keydown", (e) => this.handleKeydown(e));
      this.searchInput.on("focus", () => this.handleSearchFocus());
      this.searchInput.on("blur", () =>
        setTimeout(() => this.hideAutocomplete(), 150)
      );
      this.clearButton.on("click", () => this.clearSearch());
      this.voiceButton.on("click", () => this.startVoiceSearch());
      this.container
        .find(".sffc-search-submit, .sffc-search-submit-compact")
        .on("click", (e) => {
          e.preventDefault();
          this.executeSearch();
        });
      this.container
        .find(".sffc-feeling-lucky, .sffc-feeling-lucky-compact")
        .on("click", (e) => {
          e.preventDefault();
          this.feelingLucky();
        });
      this.autocompleteContent.on("click", ".sffc-suggestion-item", (e) =>
        this.selectSuggestion($(e.currentTarget))
      );
      this.container.closest("form").on("submit", (e) => {
        e.preventDefault();
        this.executeSearch();
      });
    }

    preloadSuggestions() {
      const cached = localStorage.getItem("sffc_suggestion_cache");
      if (cached) {
        try {
          this.localCache = JSON.parse(cached);
          this.initFuseEngines();
        } catch (e) {
          console.warn("Failed to parse cached suggestions", e);
        }
      }

      $.ajax({
        url: sffc_search.ajax_url,
        type: "GET",
        data: { action: "sffc_preload_suggestions" },
        success: (response) => {
          if (response.success) {
            this.localCache = response.data;
            this.initFuseEngines();
            localStorage.setItem(
              "sffc_suggestion_cache",
              JSON.stringify(response.data)
            );
            localStorage.setItem("sffc_suggestion_cache_time", Date.now());
          }
        },
      });
    }

    initFuseEngines() {
      if (!this.localCache) return;
      for (const [mode, list] of Object.entries(this.localCache)) {
        this.fuseInstances[mode] = new Fuse(list, {
          keys: ["text"],
          threshold: 0.4,
          distance: 50,
          minMatchCharLength: 2,
        });
      }
    }

    switchMode(button) {
      const newMode = button.data("mode");
      const placeholder = button.data("placeholder");
      const color = button.data("color");
      const normalizedMode = this.normalizeMode(newMode);

      this.modeButtons.removeClass("active");
      button.addClass("active");
      this.searchInput.attr("placeholder", placeholder);
      this.searchInput.data("mode", normalizedMode);
      this.currentMode = normalizedMode;
      this.updateThemeColor(color);

      if (!this.isResultsPageContext) {
        const searchValue = this.searchInput.val().trim();
        if (searchValue.length > 0) {
          this.executeSearch();
        } else if (searchValue.length >= 2) {
          this.performAutocomplete();
        }
      }

      this.syncActiveModeButtons();
      this.trackEvent("mode_switch", { mode: normalizedMode });
    }

    updateThemeColor(color) {
      this.container[0].style.setProperty("--search-accent-color", color);
    }

    handleSearchInput(event) {
      const query = $(event.target).val();
      this.clearButton.toggle(query.length > 0);
      if (this.searchTimeout) clearTimeout(this.searchTimeout);

      if (query.length >= 2) {
        this.searchTimeout = setTimeout(() => this.performAutocomplete(), 300);
      } else {
        this.hideAutocomplete();
      }
    }

    handleKeydown(event) {
      const key = event.key;
      if (key === "Enter") {
        event.preventDefault();
        if (this.selectedSuggestionIndex >= 0 && this.suggestions.length > 0) {
          this.selectSuggestionByIndex(this.selectedSuggestionIndex);
        } else {
          this.executeSearch();
        }
        return;
      }

      if (key === "Escape") {
        this.hideAutocomplete();
        this.selectedSuggestionIndex = -1;
        return;
      }

      if (key === "ArrowDown") {
        event.preventDefault();
        this.navigateSuggestions(1);
        return;
      }

      if (key === "ArrowUp") {
        event.preventDefault();
        this.navigateSuggestions(-1);
        return;
      }
    }

    handleSearchFocus() {
      const query = this.searchInput.val();
      if (query.length >= 2) {
        this.showAutocomplete();
      } else {
        this.showTrendingQueries();
      }
    }

    performAutocomplete() {
      const query = this.searchInput.val().trim();
      if (query.length < 2) {
        this.hideAutocomplete();
        return;
      }

      // ✅ Instant local match first
      if (this.fuseInstances[this.currentMode]) {
        const results = this.fuseInstances[this.currentMode].search(query);
        if (results.length > 0) {
          const suggestions = results.slice(0, 8).map((r) => r.item);
          this.displaySuggestions(suggestions, query);
        }
      }

      // Then background refresh from server for adaptive ranking
      this.isSearching = true;
      this.container.addClass("sffc-search-loading");

      $.ajax({
        url: sffc_search.ajax_url,
        type: "GET",
        data: {
          action: "sffc_search_autocomplete",
          q: query,
          mode: this.currentMode,
          nonce: sffc_search.nonce,
          limit: 8,
        },
        success: (response) => {
          this.isSearching = false;
          this.container.removeClass("sffc-search-loading");

          if (response.success) {
            this.displaySuggestions(response.data.suggestions, query);
          } else {
            this.hideAutocomplete();
          }
        },
        error: () => {
          this.isSearching = false;
          this.container.removeClass("sffc-search-loading");
          this.hideAutocomplete();
        },
      });
    }

    displaySuggestions(suggestions, query) {
      this.suggestions = suggestions;
      this.selectedSuggestionIndex = -1;

      if (suggestions.length === 0) {
        this.hideAutocomplete();
        return;
      }

      let html = "";
      suggestions.forEach((suggestion, index) => {
        const highlightedText = this.highlightQuery(suggestion.text, query);
        const defaultIcon =
          '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>';
        html += `
                    <div class="sffc-suggestion-item" data-index="${index}" data-text="${
          suggestion.text
        }">
                        <div class="sffc-suggestion-icon">${
                          suggestion.icon || defaultIcon
                        }</div>
                        <div class="sffc-suggestion-content">
                            <div class="sffc-suggestion-text">${highlightedText}</div>
                        </div>
                    </div>
                `;
      });

      this.autocompleteContent.html(html);
      this.showAutocomplete();
    }

    highlightQuery(text, query) {
      if (!query) return text;
      const regex = new RegExp(
        `(${query.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`,
        "gi"
      );
      return text.replace(
        regex,
        '<span class="sffc-suggestion-highlight">$1</span>'
      );
    }

    navigateSuggestions(direction) {
      if (this.suggestions.length === 0) return;

      this.autocompleteContent
        .find(".sffc-suggestion-item")
        .removeClass("highlighted");
      this.selectedSuggestionIndex += direction;

      if (this.selectedSuggestionIndex >= this.suggestions.length) {
        this.selectedSuggestionIndex = -1;
      } else if (this.selectedSuggestionIndex < -1) {
        this.selectedSuggestionIndex = this.suggestions.length - 1;
      }

      if (this.selectedSuggestionIndex >= 0) {
        const suggestionItem = this.autocompleteContent.find(
          `.sffc-suggestion-item[data-index="${this.selectedSuggestionIndex}"]`
        );
        suggestionItem.addClass("highlighted");
        const suggestionText =
          this.suggestions[this.selectedSuggestionIndex].text;
        this.searchInput.val(suggestionText);
      }
    }

    selectSuggestion(suggestionItem) {
      const suggestionText = suggestionItem.data("text");
      this.searchInput.val(suggestionText);
      this.hideAutocomplete();
      this.executeSearch();
      this.trackEvent("suggestion_select", {
        text: suggestionText,
        mode: this.currentMode,
      });
    }

    selectSuggestionByIndex(index) {
      if (index >= 0 && index < this.suggestions.length) {
        const suggestion = this.suggestions[index];
        this.searchInput.val(suggestion.text);
        this.hideAutocomplete();
        this.executeSearch();
      }
    }

    showAutocomplete() {
      this.autocompleteDropdown.show();
    }

    hideAutocomplete() {
      this.autocompleteDropdown.hide();
      this.selectedSuggestionIndex = -1;
    }

    showTrendingQueries() {
      const trendingQueries = [
        { text: "Private equity associate London" },
        { text: "Blackstone acquisition" },
        { text: "KKR portfolio companies" },
        { text: "Mid-market buyout" },
      ];

      let html = "";
      trendingQueries.forEach((query, index) => {
        html += `
                    <div class="sffc-suggestion-item" data-index="${index}" data-text="${query.text}">
                        <div class="sffc-suggestion-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg></div>
                        <div class="sffc-suggestion-content"><div class="sffc-suggestion-text">${query.text}</div></div>
                    </div>
                `;
      });

      this.autocompleteContent.html(html);
      this.suggestions = trendingQueries;
      this.showAutocomplete();
    }

    clearSearch() {
      this.searchInput.val("").focus();
      this.hideAutocomplete();
      this.trackEvent("search_clear");
    }

    /**
     * Clean query string to remove duplicates and excessive repetition
     */
    cleanQuery(query) {
      if (!query || typeof query !== 'string') {
        return '';
      }

      // Basic sanitization
      query = query.trim();
      
      if (query === '') {
        return '';
      }

      // Split by common delimiters and whitespace
      const terms = query.split(/[\s,+&]+/);
      
      // Remove empty terms and duplicates (case-insensitive)
      const uniqueTerms = [];
      const seenTerms = new Set();
      
      for (const term of terms) {
        const cleanTerm = term.trim();
        const lowerTerm = cleanTerm.toLowerCase();
        
        if (cleanTerm && !seenTerms.has(lowerTerm)) {
          uniqueTerms.push(cleanTerm);
          seenTerms.add(lowerTerm);
        }
      }
      
      // Remove excessive repetition patterns
      const finalTerms = [];
      let prevTerm = '';
      
      for (const term of uniqueTerms) {
        if (term.toLowerCase() !== prevTerm.toLowerCase() && term.length > 1) {
          finalTerms.push(term);
          prevTerm = term;
        }
      }
      
      // Rejoin and limit length
      const cleaned = finalTerms.join(' ');
      return cleaned.substring(0, 150); // Prevent extremely long URLs
    }

    executeSearch() {
      const query = this.cleanQuery(this.searchInput.val().trim());
      if (!query) return this.searchInput.focus();

      const resultsPage = this.searchInput.data("results-page");
      this.trackEvent("search_execute", { query, mode: this.currentMode });

      $.ajax({
        url: sffc_search.ajax_url,
        type: "POST",
        data: {
          action: "sffc_execute_search",
          q: query,
          mode: this.currentMode,
          results_page: resultsPage,
          nonce: sffc_search.nonce,
        },
        success: (response) => {
          if (response.success && response.data.redirect_url) {
            window.location.href = response.data.redirect_url;
          } else {
            this.fallbackSearch(query);
          }
        },
        error: () => this.fallbackSearch(query),
      });
    }

    fallbackSearch(query) {
      const cleanedQuery = this.cleanQuery(query);
      const params = new URLSearchParams({
        q: cleanedQuery,
        mode: this.currentMode,
        search: "1",
      });
      const resultsPageUrl =
        this.container.data("results-page") || "/search-results/";
      window.location.href = `${resultsPageUrl}?${params.toString()}`;
    }

    feelingLucky() {
      const query = this.searchInput.val().trim();
      if (query) {
        this.trackEvent("feeling_lucky", { query, mode: this.currentMode });
        this.executeSearch();
      } else {
        const trendingQueries = [
          "Private equity London",
          "Blackstone",
          "KKR deals",
          "Mid-market",
        ];
        const randomQuery =
          trendingQueries[Math.floor(Math.random() * trendingQueries.length)];
        this.searchInput.val(randomQuery);
        this.executeSearch();
      }
    }

    setupVoiceSearch() {
      if (
        !("webkitSpeechRecognition" in window) &&
        !("SpeechRecognition" in window)
      ) {
        this.voiceButton.hide();
        return;
      }

      const SpeechRecognition =
        window.SpeechRecognition || window.webkitSpeechRecognition;
      this.recognition = new SpeechRecognition();

      this.recognition.continuous = false;
      this.recognition.interimResults = false;
      this.recognition.lang = "en-US";

      this.recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        this.searchInput.val(transcript);
        this.voiceButton.removeClass("sffc-voice-listening");
        setTimeout(() => this.executeSearch(), 500);
        this.trackEvent("voice_search", { transcript });
      };

      this.recognition.onerror = () =>
        this.voiceButton.removeClass("sffc-voice-listening");
      this.recognition.onend = () =>
        this.voiceButton.removeClass("sffc-voice-listening");
    }

    startVoiceSearch() {
      if (!this.recognition) {
        alert(sffc_search.strings.voice_not_supported);
        return;
      }

      this.voiceButton.addClass("sffc-voice-listening");
      this.recognition.start();
      this.trackEvent("voice_search_start");
    }

    loadTrendingQueries() {
      // Placeholder for backend trending load
    }

    normalizeMode(mode) {
      if (!mode) return "jobs";
      if (typeof mode === "string" && mode.toLowerCase() === "news")
        return "insights";
      return mode;
    }

    syncActiveModeButtons() {
      const activeMode = this.currentMode;
      if (!activeMode) return;

      this.modeButtons.each((_, el) => {
        const $b = $(el);
        const isActive = $b.data("mode") === activeMode;
        $b.toggleClass("active", isActive);
        if ($b.attr("aria-pressed") !== undefined) {
          $b.attr("aria-pressed", isActive ? "true" : "false");
        }
      });

      const activeButton = this.modeButtons
        .filter((_, el) => $(el).data("mode") === activeMode)
        .first();
      if (activeButton.length) {
        const placeholder = activeButton.data("placeholder");
        const color = activeButton.data("color");
        if (placeholder) this.searchInput.attr("placeholder", placeholder);
        if (color) this.updateThemeColor(color);
      }
    }

    trackEvent(event, data = {}) {
      if (typeof gtag !== "undefined") {
        gtag("event", event, { event_category: "PE Search", ...data });
      }
    }
  }

  $(document).ready(() => {
    $(".sffc-pe-search-container, .sffc-pe-search-compact").each(function () {
      new PESearchInterface(this);
    });
  });

  const voiceCSS = `
        .sffc-voice-search.sffc-voice-listening {
            animation: pulse 1.5s infinite;
            color: #dc2626 !important;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .sffc-pe-search-container { --search-accent-color: #1a73e8; }
        .sffc-mode-tab.active { color: var(--search-accent-color) !important; }
    `;
  $("<style>").text(voiceCSS).appendTo("head");
})(jQuery);
