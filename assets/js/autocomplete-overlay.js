/**
 * Autocomplete Overlay for MENA Careers Conversational
 * Provides suggestions that integrate with the legacy senna-conversational system
 */

(function ($) {
  "use strict";

  class AutocompleteOverlay {
    constructor() {
      this.queryLibrary = {
        jobSearch: {
          title: "Finance Search",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>',
          queries: [
            {
              text: "Show me finance director roles",
              sub: "Senior finance",
            },
            {
              text: "Show me VP finance roles",
              sub: "Senior finance",
            },
            { text: "Show me CFO roles", sub: "Finance leadership" },
            { text: "Show me corporate finance roles", sub: "Corporate finance" },
            { text: "Show me FP&A director roles", sub: "Planning and analysis" },
            { text: "Show me investor relations roles", sub: "Capital markets and IR" },
            { text: "Show me treasury roles", sub: "Treasury leadership" },
            { text: "Show me strategy and M&A roles", sub: "Strategic finance" },
            { text: "Show me finance transformation roles", sub: "Systems and change" },
            { text: "Show me all senior finance opportunities", sub: "Browse finance roles" },
          ],
        },
        careerPlaybooks: {
          title: "Career Playbooks",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v19H6.5A2.5 2.5 0 0 0 4 23.5z"></path><path d="M8 6h9"></path><path d="M8 10h9"></path><path d="M8 14h5"></path></svg>',
          queries: [
            {
              text: "30-60-90 plan for a new finance director",
              sub: "Leadership onboarding",
            },
            {
              text: "30-60-90 plan for a new VP finance",
              sub: "Executive onboarding",
            },
            {
              text: "Promotion roadmap from manager to director",
              sub: "Career progression",
            },
            {
              text: "Skills gap analysis for senior finance interviews",
              sub: "Professional development",
            },
            {
              text: "Case interview drill for investment and portfolio decisions",
              sub: "Technical prep",
            },
          ],
        },
        locationBased: {
          title: "Senior Finance Search",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>',
          queries: [
            { text: "Finance director jobs", sub: "Senior finance leadership" },
            { text: "VP finance jobs", sub: "Executive finance" },
            { text: "Head of FP&A jobs", sub: "Planning leadership" },
            { text: "Treasury director jobs", sub: "Treasury leadership" },
            { text: "Corporate development jobs", sub: "Strategic finance" },
            { text: "Controller jobs", sub: "Controllership leadership" },
            { text: "CFO jobs", sub: "Top finance roles" },
            { text: "Finance transformation jobs", sub: "Change and systems" },
            { text: "Investor relations jobs", sub: "Capital markets roles" },
            { text: "Finance recruiter contacts", sub: "Finance hiring contacts" },
          ],
        },
        salaryBased: {
          title: "Salary & Compensation",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>',
          queries: [
            { text: "Jobs paying over 200k", sub: "High compensation" },
            { text: "Jobs paying over 150k", sub: "Senior salaries" },
            { text: "Jobs paying over 100k", sub: "Good compensation" },
            { text: "Finance director compensation", sub: "Market pay" },
            { text: "VP finance compensation", sub: "Compensation" },
            { text: "Bonus benchmarks for senior finance", sub: "Compensation benchmarks" },
            { text: "Highest paying roles", sub: "Top compensation" },
          ],
        },
        industrySpecific: {
          title: "Industry & Sector",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><rect x="9" y="7" width="2" height="2"></rect><rect x="13" y="7" width="2" height="2"></rect></svg>',
          queries: [
            { text: "Asset management roles", sub: "Core AM opportunities" },
            { text: "Private equity roles", sub: "Investing opportunities" },
            { text: "Investment banking roles", sub: "IB positions" },
            { text: "Private credit roles", sub: "Capital solutions" },
            { text: "Wealth management roles", sub: "Client portfolio roles" },
            { text: "Markets and sales roles", sub: "S&T opportunities" },
            { text: "Risk and compliance jobs", sub: "Control functions" },
            { text: "Corporate banking roles", sub: "Banking coverage" },
            { text: "Fintech positions", sub: "Technology + Finance" },
            { text: "ESG investing roles", sub: "Sustainable finance" },
            { text: "Accounting and audit roles", sub: "Professional services" },
          ],
        },
        professionalBrand: {
          title: "Networking & Outreach",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>',
          queries: [
            {
              text: "Draft recruiter outreach for finance director roles",
              sub: "Finance recruiter intro",
            },
            {
              text: "Draft recruiter outreach for VP finance roles",
              sub: "Finance recruiter intro",
            },
            {
              text: "Follow-up after an asset management interview",
              sub: "Post-interview",
            },
            {
              text: "Networking message to senior finance leaders",
              sub: "Relationship building",
            },
            {
              text: "LinkedIn message for a finance headhunter",
              sub: "Outreach prep",
            },
          ],
        },
        companySpecific: {
          title: "Company Search",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><path d="M9 7h2v2H9zM13 7h2v2h-2zM9 11h2v2H9zM13 11h2v2h-2z"></path></svg>',
          queries: [
            { text: "Jobs at Goldman Sachs", sub: "Bulge bracket" },
            { text: "Jobs at Morgan Stanley", sub: "Investment bank" },
            { text: "Jobs at JP Morgan", sub: "Global bank" },
            { text: "Jobs at KKR", sub: "Alternative investments" },
            { text: "Jobs at HSBC", sub: "Global bank" },
            { text: "Jobs at BlackRock", sub: "Asset management" },
            { text: "Jobs at Lazard", sub: "Advisory" },
            { text: "Boutique investment banks", sub: "Smaller firms" },
          ],
        },
        careerAdvice: {
          title: "Career Advice",
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
          queries: [
            { text: "How to move into senior finance", sub: "Finance career guide" },
            {
              text: "How to move from manager to finance director",
              sub: "Finance career guide",
            },
            { text: "Asset management interview prep", sub: "Interview tips" },
            { text: "Senior finance interview prep", sub: "Interview tips" },
            { text: "Skills needed for finance director roles", sub: "Requirements" },
            { text: "Skills needed for VP finance roles", sub: "Requirements" },
            { text: "Moving from banking into finance leadership", sub: "Career paths" },
            { text: "Salary negotiation tips", sub: "Comp discussion" },
            { text: "Best finance employers to target", sub: "Where to start" },
          ],
        },
      };

      this.selectedIndex = -1;
      this.isShowingSuggestions = false;
      this.currentQuery = "";

      this.init();
    }

    init() {
      this.bindEvents();
      this.injectStyles();
    }

    bindEvents() {
      const self = this;

      // Input events
      $("#senna-input").on("input", function () {
        self.currentQuery = $(this).val();
        self.showSuggestions();
      });

      $("#senna-input").on("focus", function () {
        if (!self.currentQuery) {
          self.showSuggestions();
        }
      });

      $("#senna-input").on("blur", function () {
        setTimeout(() => {
          if (
            !$(document.activeElement).closest(".sffc-autocomplete-suggestions")
              .length
          ) {
            self.hideSuggestions();
          }
        }, 200);
      });

      // Keyboard navigation
      $("#senna-input").on("keydown", function (e) {
        if (!self.isShowingSuggestions) return;

        const $items = $("#autocomplete-suggestions .suggestion-item");

        switch (e.key) {
          case "ArrowDown":
            e.preventDefault();
            self.selectedIndex = Math.min(
              self.selectedIndex + 1,
              $items.length - 1
            );
            self.selectSuggestion(self.selectedIndex);
            break;

          case "ArrowUp":
            e.preventDefault();
            self.selectedIndex = Math.max(self.selectedIndex - 1, -1);
            self.selectSuggestion(self.selectedIndex);
            break;

          case "Enter":
            if (self.selectedIndex >= 0) {
              e.preventDefault();
              e.stopPropagation();
              const query = $items.eq(self.selectedIndex).data("query");
              self.selectQuery(query);
              return false;
            }
            break;

          case "Escape":
            self.hideSuggestions();
            break;
        }
      });

      // Click on suggestion
      $(document).on("click", ".suggestion-item", function (e) {
        e.preventDefault();
        e.stopPropagation();
        const query = $(this).data("query");
        self.selectQuery(query);
      });
    }

    selectQuery(query) {
      $("#senna-input").val(query);
      this.hideSuggestions();

      // Trigger senna-conversational's handler
      if (
        window.sennaConversational &&
        window.sennaConversational.handleUserInput
      ) {
        window.sennaConversational.handleUserInput();
      } else {
        // Fallback - trigger enter key
        const e = $.Event("keypress");
        e.which = 13;
        $("#senna-input").trigger(e);
      }
    }

    showSuggestions() {
      const $container = $("#autocomplete-suggestions");
      const filter = this.currentQuery.toLowerCase();
      let html = "";

      if (filter) {
        // Filter suggestions based on input
        Object.entries(this.queryLibrary).forEach(([key, category]) => {
          let categoryItems = "";

          category.queries.forEach((query) => {
            if (
              query.text.toLowerCase().includes(filter) ||
              query.sub.toLowerCase().includes(filter)
            ) {
              categoryItems += this.createSuggestionItem(query, category.icon);
            }
          });

          if (categoryItems) {
            html += `<div class="suggestion-category">${category.title}</div>`;
            html += categoryItems;
          }
        });

        // Add custom search option if no exact matches
        if (!html) {
          html = this.createSuggestionItem(
            {
              text: this.currentQuery,
              sub: "Search for this",
            },
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>'
          );
        }
      } else {
        // Show popular suggestions when empty
        html += '<div class="suggestion-category">Popular Searches</div>';
        const popularQueries = [
          { text: "Show me finance director roles", sub: "Senior finance" },
          {
            text: "Show me VP finance roles",
            sub: "Senior finance",
          },
          {
            text: "Draft recruiter outreach for senior finance roles",
            sub: "Career outreach",
          },
          { text: "Show me corporate finance roles", sub: "Corporate finance" },
          { text: "Show me all senior finance opportunities", sub: "Browse finance roles" },
        ];
        popularQueries.forEach((query) => {
          html += this.createSuggestionItem(
            query,
            this.queryLibrary.jobSearch.icon
          );
        });
      }

      $container.html(html).addClass("active");
      this.isShowingSuggestions = true;
      this.selectedIndex = -1;
    }

    createSuggestionItem(query, icon) {
      return `
                <div class="suggestion-item" data-query="${query.text.replace(
                  /"/g,
                  "&quot;"
                )}">
                    <div class="suggestion-icon">${icon}</div>
                    <div class="suggestion-text">
                        <div class="suggestion-main">${this.highlightMatch(
                          query.text
                        )}</div>
                        <div class="suggestion-sub">${query.sub}</div>
                    </div>
                </div>
            `;
    }

    highlightMatch(text) {
      if (!this.currentQuery) return text;
      // Escape special regex characters
      const escapedQuery = this.currentQuery.replace(
        /[.*+?^${}()|[\]\\]/g,
        "\\$&"
      );
      const regex = new RegExp(`(${escapedQuery})`, "gi");
      return text.replace(regex, "<strong>$1</strong>");
    }

    hideSuggestions() {
      $("#autocomplete-suggestions").removeClass("active");
      this.isShowingSuggestions = false;
      this.selectedIndex = -1;
    }

    selectSuggestion(index) {
      const $items = $("#autocomplete-suggestions .suggestion-item");
      $items.removeClass("selected");

      if (index >= 0 && index < $items.length) {
        $items.eq(index).addClass("selected");
      }
    }

    injectStyles() {
      if ($("#autocomplete-overlay-styles").length) return;

      const styles = `
                <style id="autocomplete-overlay-styles">
                .suggestion-item.selected {
                    background: linear-gradient(90deg, rgba(45, 106, 79, 0.12), rgba(45, 106, 79, 0.06));
                    padding-left: 24px;
                }
                .suggestion-main strong {
                    color: #2D6A4F;
                    font-weight: 600;
                }
                #senna-input:focus {
                    border-color: rgba(45, 106, 79, 0.3);
                    box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.1);
                }
                </style>
            `;
      $("head").append(styles);
    }
  }

  // Initialize when DOM is ready
  $(document).ready(function () {
    // Wait for senna-conversational to load first
    setTimeout(() => {
      if ($("#senna-input").length) {
        window.autocompleteOverlay = new AutocompleteOverlay();
      }
    }, 500);
  });
})(jQuery);
