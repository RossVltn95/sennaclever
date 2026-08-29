/**
 * PE Filter Suggestions Component
 * Version: 1.0.0
 * Description: Smart filter suggestions based on user behavior
 */

(function($) {
    'use strict';

    class PEFilterSuggestions {
        constructor() {
            this.suggestionsVisible = false;
            this.currentSuggestions = [];
            this.init();
        }

        init() {
            // Wait for filters to be ready
            document.addEventListener('peFiltersApplied', (e) => {
                this.updateSuggestions(e.detail);
            });

            // Monitor filter bar
            this.observeFilterBar();
        }

        observeFilterBar() {
            const observer = new MutationObserver(() => {
                const filterBar = document.querySelector('.pe-filter-bar');
                if (filterBar && !document.querySelector('.pe-suggestions-container')) {
                    this.createSuggestionsUI();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        createSuggestionsUI() {
            const filterContainer = document.querySelector('#pe-filter-container');
            if (!filterContainer) return;

            const suggestionsHTML = `
                <div class="pe-suggestions-container" style="display: none;">
                    <div class="pe-suggestions-header">
                        <span class="pe-suggestions-icon">💡</span>
                        <span class="pe-suggestions-title">Suggested Filters</span>
                        <button class="pe-suggestions-close">×</button>
                    </div>
                    <div class="pe-suggestions-list"></div>
                </div>
            `;

            const suggestionsDiv = document.createElement('div');
            suggestionsDiv.innerHTML = suggestionsHTML;
            filterContainer.appendChild(suggestionsDiv.firstElementChild);

            this.bindSuggestionEvents();
        }

        bindSuggestionEvents() {
            const closeBtn = document.querySelector('.pe-suggestions-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hideSuggestions());
            }
        }

        updateSuggestions(filterResults) {
            // Only show suggestions if results are limited
            if (filterResults.visible < 10 && filterResults.visible > 0) {
                this.generateSuggestions();
            } else if (filterResults.visible === 0) {
                this.showNoResultsSuggestions();
            }
        }

        generateSuggestions() {
            if (!window.peFilters) return;

            const suggestions = [];
            const activeFilters = window.peFilters.activeFilters;

            // Suggest removing most restrictive filter
            if (this.hasMultipleActiveFilters(activeFilters)) {
                suggestions.push({
                    type: 'remove',
                    message: 'Try removing some filters for more results',
                    action: 'clear_restrictive'
                });
            }

            // Suggest related filters
            if (activeFilters.fundSize === 'mega' && !activeFilters.location) {
                suggestions.push({
                    type: 'add',
                    filter: 'location',
                    value: 'london',
                    message: 'Most mega-cap funds have London offices'
                });
            }

            if (activeFilters.location === 'nordic' && !activeFilters.fundSize) {
                suggestions.push({
                    type: 'add',
                    filter: 'fundSize',
                    value: 'large',
                    message: 'Nordic region has many large-cap funds'
                });
            }

            this.displaySuggestions(suggestions);
        }

        showNoResultsSuggestions() {
            const suggestions = [{
                type: 'clear',
                message: 'No matches found. Try adjusting your filters',
                action: 'clear_all'
            }];

            this.displaySuggestions(suggestions);
        }

        displaySuggestions(suggestions) {
            const container = document.querySelector('.pe-suggestions-container');
            const list = document.querySelector('.pe-suggestions-list');
            
            if (!container || !list) return;

            // Clear existing
            list.innerHTML = '';

            suggestions.forEach(suggestion => {
                const suggestionEl = document.createElement('div');
                suggestionEl.className = 'pe-suggestion-item';
                
                if (suggestion.type === 'add') {
                    suggestionEl.innerHTML = `
                        <span class="pe-suggestion-text">${suggestion.message}</span>
                        <button class="pe-suggestion-apply" data-filter="${suggestion.filter}" data-value="${suggestion.value}">
                            Apply
                        </button>
                    `;
                } else if (suggestion.type === 'remove' || suggestion.type === 'clear') {
                    suggestionEl.innerHTML = `
                        <span class="pe-suggestion-text">${suggestion.message}</span>
                        <button class="pe-suggestion-apply" data-action="${suggestion.action}">
                            ${suggestion.type === 'clear' ? 'Clear All' : 'Adjust'}
                        </button>
                    `;
                }

                list.appendChild(suggestionEl);
            });

            // Bind apply buttons
            list.querySelectorAll('.pe-suggestion-apply').forEach(btn => {
                btn.addEventListener('click', (e) => this.applySuggestion(e));
            });

            // Show container
            container.style.display = 'block';
            this.suggestionsVisible = true;
        }

        applySuggestion(e) {
            const btn = e.target;
            const filter = btn.dataset.filter;
            const value = btn.dataset.value;
            const action = btn.dataset.action;

            if (filter && value && window.peFilters) {
                // Apply the suggested filter
                window.peFilters.activeFilters[filter] = value;
                window.peFilters.applyFilters();
                
                // Update UI
                this.updateFilterUI(filter, value);
            } else if (action === 'clear_all' && window.peFilters) {
                window.peFilters.clearAllFilters();
            } else if (action === 'clear_restrictive' && window.peFilters) {
                // Clear the most recently added filter
                this.clearMostRestrictive();
            }

            this.hideSuggestions();
        }

        updateFilterUI(filterType, value) {
            const dropdown = document.querySelector(`[data-filter="${filterType}"]`);
            if (!dropdown) return;

            const button = dropdown.querySelector('.pe-filter-button');
            const item = dropdown.querySelector(`[data-value="${value}"]`);
            
            if (button && item) {
                // Update button text
                const itemText = item.querySelector('span:first-child').textContent.split(' ')[0];
                button.querySelector('span:first-child').textContent = itemText;
                button.classList.add('active');
                
                // Mark item as selected
                dropdown.querySelectorAll('.pe-dropdown-item').forEach(i => {
                    i.classList.remove('selected');
                });
                item.classList.add('selected');
            }
        }

        clearMostRestrictive() {
            if (!window.peFilters) return;

            const activeFilters = window.peFilters.activeFilters;
            const filterOrder = ['workStyle', 'geoFocus', 'fundSize', 'seniority', 'location'];
            
            for (const filter of filterOrder) {
                if (activeFilters[filter]) {
                    activeFilters[filter] = null;
                    window.peFilters.applyFilters();
                    
                    // Update UI
                    const dropdown = document.querySelector(`[data-filter="${filter}"]`);
                    if (dropdown) {
                        const button = dropdown.querySelector('.pe-filter-button');
                        if (button) {
                            button.classList.remove('active');
                            const defaultText = filter.charAt(0).toUpperCase() + 
                                              filter.slice(1).replace(/([A-Z])/g, ' $1');
                            button.querySelector('span:first-child').textContent = defaultText;
                        }
                    }
                    break;
                }
            }
        }

        hasMultipleActiveFilters(filters) {
            let count = 0;
            Object.keys(filters).forEach(key => {
                if (filters[key] && filters[key] !== null && 
                    (!Array.isArray(filters[key]) || filters[key].length > 0)) {
                    count++;
                }
            });
            return count >= 2;
        }

        hideSuggestions() {
            const container = document.querySelector('.pe-suggestions-container');
            if (container) {
                container.style.display = 'none';
            }
            this.suggestionsVisible = false;
        }
    }

    // Add styles
    const styles = `
        <style>
        .pe-suggestions-container {
            background: linear-gradient(135deg, #FFF9E6 0%, #FFF5D6 100%);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-top: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pe-suggestions-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #1A3028;
        }

        .pe-suggestions-icon {
            font-size: 16px;
        }

        .pe-suggestions-title {
            flex: 1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pe-suggestions-close {
            background: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 20px;
            height: 20px;
        }

        .pe-suggestions-close:hover {
            color: #1A3028;
        }

        .pe-suggestions-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .pe-suggestion-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #1A3028;
        }

        .pe-suggestion-text {
            flex: 1;
        }

        .pe-suggestion-apply {
            background: linear-gradient(135deg, #2D6A4F 0%, #1B4332 100%);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pe-suggestion-apply:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.3);
        }
        </style>
    `;

    // DISABLED - Filter suggestions not needed
    // Inject styles
    // document.head.insertAdjacentHTML('beforeend', styles);

    // Initialize
    // window.PEFilterSuggestions = PEFilterSuggestions;
    // window.peFilterSuggestions = new PEFilterSuggestions();

})(jQuery);