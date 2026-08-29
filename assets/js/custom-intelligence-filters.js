/**
 * Custom Intelligence Filters Frontend
 * Extends ZENA's PE filter system with custom intelligence filters
 * 
 * @package SkillFarmFinance
 * @since 10.20.0
 */

(function($) {
    'use strict';
    
    class CustomIntelligenceFilters {
        constructor() {
            this.config = window.sffc_custom_intelligence || {};
            this.filterTypes = this.config.filter_types || {};
            this.activeFilter = null;
            this.isLoading = false;
            
            console.log('🎯 Custom Intelligence Filters: Initializing...');
            
            this.init();
        }
        
        init() {
            // Wait for ZENA's PE filter system to be ready
            this.waitForPEFilters();
            
            // Listen for custom events
            document.addEventListener('peFilterContainerReady', () => {
                console.log('🎯 Custom Intelligence: PE container ready');
                this.extendFilterSystem();
            });
            
            // Also check if container already exists
            if (document.querySelector('#pe-filter-container')) {
                this.extendFilterSystem();
            }
        }
        
        waitForPEFilters() {
            let attempts = 0;
            const maxAttempts = 20;
            
            const checkPEFilters = setInterval(() => {
                attempts++;
                
                // Look for ZENA's PE filter system
                if (window.PEFilters || document.querySelector('.pe-main-filters')) {
                    clearInterval(checkPEFilters);
                    console.log('🎯 Custom Intelligence: ZENA PE Filters detected');
                    setTimeout(() => this.extendFilterSystem(), 500);
                    return;
                }
                
                if (attempts >= maxAttempts) {
                    clearInterval(checkPEFilters);
                    console.log('🎯 Custom Intelligence: Creating standalone filter system');
                    this.createStandaloneSystem();
                }
            }, 250);
        }
        
        extendFilterSystem() {
            console.log('🎯 Custom Intelligence: Extending ZENA filter system...');
            
            // Find the PE filter container
            const container = document.querySelector('#pe-filter-container, .pe-filter-sidebar');
            if (!container) {
                console.warn('🎯 Custom Intelligence: No PE filter container found');
                return;
            }
            
            // Add custom filters to existing system
            this.addCustomFiltersToContainer(container);
            
            // Hook into ZENA's filter events
            this.hookIntoZENAEvents();
            
            console.log('🎯 Custom Intelligence: Successfully extended ZENA filter system');
        }
        
        addCustomFiltersToContainer(container) {
            // Find or create the main filters container
            let mainFilters = container.querySelector('.pe-main-filters');
            if (!mainFilters) {
                mainFilters = document.createElement('div');
                mainFilters.className = 'pe-main-filters';
                container.appendChild(mainFilters);
            }
            
            // Add custom intelligence section
            const customSection = this.createCustomIntelligenceSection();
            mainFilters.appendChild(customSection);
        }
        
        createCustomIntelligenceSection() {
            const section = document.createElement('div');
            section.className = 'pe-filter-section custom-intelligence-section';
            section.innerHTML = `
                <div class="pe-section-header">
                    <h3>Custom Intelligence</h3>
                    <span class="section-description">Advanced market intelligence filters</span>
                </div>
                <div class="pe-filter-grid custom-intelligence-grid">
                    ${this.generateCustomFilterCards()}
                </div>
            `;
            
            // Add event listeners
            this.bindCustomFilterEvents(section);
            
            return section;
        }
        
        generateCustomFilterCards() {
            if (Object.keys(this.filterTypes).length === 0) {
                return '<div class="no-custom-filters">No custom filters configured</div>';
            }
            
            return Object.entries(this.filterTypes)
                .filter(([key, filter]) => filter.active)
                .sort((a, b) => (a[1].priority || 999) - (b[1].priority || 999))
                .map(([key, filter]) => this.createFilterCard(key, filter))
                .join('');
        }
        
        createFilterCard(key, filter) {
            return `
                <div class="pe-filter-card custom-intelligence-filter" 
                     data-filter-type="${key}" 
                     data-filter-category="custom"
                     style="border-left: 4px solid ${filter.color || '#007cba'}">
                    <div class="filter-icon">${filter.icon || '📊'}</div>
                    <div class="filter-content">
                        <div class="filter-title">${filter.label}</div>
                        <div class="filter-description">Custom ${filter.label} intelligence</div>
                    </div>
                    <div class="filter-status">
                        <span class="filter-count" data-filter="${key}">-</span>
                    </div>
                </div>
            `;
        }
        
        bindCustomFilterEvents(section) {
            const filterCards = section.querySelectorAll('.custom-intelligence-filter');
            
            filterCards.forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const filterType = card.dataset.filterType;
                    this.handleCustomFilterClick(filterType, card);
                });
            });
        }
        
        handleCustomFilterClick(filterType, card) {
            if (this.isLoading) {
                console.log('🎯 Custom Intelligence: Already loading, ignoring click');
                return;
            }
            
            console.log('🎯 Custom Intelligence: Filter clicked:', filterType);
            
            // Update active state
            this.updateActiveFilterState(card);
            
            // Load custom intelligence cards
            this.loadCustomIntelligenceCards(filterType);
        }
        
        updateActiveFilterState(activeCard) {
            // Remove active state from all filter cards
            document.querySelectorAll('.pe-filter-card').forEach(card => {
                card.classList.remove('active', 'selected');
            });
            
            // Add active state to clicked card
            activeCard.classList.add('active', 'selected');
            
            this.activeFilter = activeCard.dataset.filterType;
        }
        
        loadCustomIntelligenceCards(filterType) {
            this.isLoading = true;
            
            console.log('🎯 Custom Intelligence: Loading cards for filter:', filterType);
            
            // Show loading state
            this.showLoadingState();
            
            // Make AJAX request
            const data = {
                action: 'sffc_custom_intelligence_filter',
                filter_type: filterType,
                limit: 12,
                nonce: this.config.nonce
            };
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    console.log('🎯 Custom Intelligence: AJAX response:', response);
                    
                    if (response.success) {
                        this.displayIntelligenceCards(response.data.html, filterType);
                        this.updateFilterCount(filterType, response.data.count);
                    } else {
                        this.showErrorState(response.data || 'Failed to load intelligence cards');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🎯 Custom Intelligence: AJAX error:', error);
                    this.showErrorState('Network error loading intelligence cards');
                },
                complete: () => {
                    this.isLoading = false;
                }
            });
        }
        
        showLoadingState() {
            const container = this.getCardsContainer();
            if (container) {
                container.innerHTML = `
                    <div class="custom-intelligence-loading">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Loading custom intelligence...</div>
                    </div>
                `;
            }
        }
        
        displayIntelligenceCards(html, filterType) {
            const container = this.getCardsContainer();
            if (!container) {
                console.error('🎯 Custom Intelligence: No cards container found');
                return;
            }
            
            container.innerHTML = html;
            
            // Add custom class for styling
            container.classList.add('custom-intelligence-active');
            
            // Trigger custom event
            document.dispatchEvent(new CustomEvent('customIntelligenceCardsLoaded', {
                detail: { 
                    filterType: filterType,
                    container: container,
                    cardCount: container.querySelectorAll('.sffc-intelligence-card').length
                }
            }));
            
            // Initialize card interactions
            this.initializeCardInteractions(container);
            
            console.log('🎯 Custom Intelligence: Cards displayed successfully');
        }
        
        showErrorState(message) {
            const container = this.getCardsContainer();
            if (container) {
                container.innerHTML = `
                    <div class="custom-intelligence-error">
                        <div class="error-icon">⚠️</div>
                        <div class="error-message">${message}</div>
                        <button class="retry-button" onclick="location.reload()">Retry</button>
                    </div>
                `;
            }
        }
        
        getCardsContainer() {
            // Try multiple possible containers for maximum compatibility
            return document.querySelector(
                '.sffc-intelligence-cards, .sffc-cards-grid, .pe-cards-container, ' +
                '.intelligence-cards-container, .senna-conversation-area .cards-container, ' +
                '#senna-conversation .cards-container, .sffc-main-container .cards-container'
            );
        }
        
        updateFilterCount(filterType, count) {
            const countElement = document.querySelector(`[data-filter="${filterType}"] .filter-count`);
            if (countElement) {
                countElement.textContent = count;
            }
        }
        
        initializeCardInteractions(container) {
            // Initialize action buttons
            const actionButtons = container.querySelectorAll('.sffc-action-btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleCardAction(button);
                });
            });
            
            // Initialize card interest/dismiss buttons
            const interestButtons = container.querySelectorAll('.sffc-card-interest');
            interestButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleCardInterest(button);
                });
            });
            
            const dismissButtons = container.querySelectorAll('.sffc-card-dismiss');
            dismissButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleCardDismiss(button);
                });
            });
        }
        
        handleCardAction(button) {
            const action = button.dataset.action;
            const cardId = button.dataset.cardId;
            const card = button.closest('.sffc-intelligence-card');
            
            console.log('🎯 Custom Intelligence: Card action:', action, cardId);
            
            // Integrate with ZENA's action system or MENA Careers chat
            if (window.SennaChat && typeof window.SennaChat.send === 'function') {
                const cardTitle = card.querySelector('.sffc-card-headline')?.textContent || 'Intelligence Card';
                const prompt = this.generateActionPrompt(action, cardTitle, cardId);
                window.SennaChat.send(prompt);
            } else if (window.ActionCardsSystem) {
                window.ActionCardsSystem.handleAction(action, cardId);
            } else {
                console.log('🎯 Custom Intelligence: No action handler available');
            }
        }
        
        generateActionPrompt(action, cardTitle, cardId) {
            const prompts = {
                analyze: `Please provide a deep analysis of "${cardTitle}". I'd like to understand the strategic implications and key insights.`,
                save: `I want to save this intelligence card: "${cardTitle}" for future reference.`,
                share: `Can you help me create a summary of "${cardTitle}" that I can share with my team?`,
                track: `I'd like to track developments related to "${cardTitle}". Can you set up alerts or monitoring for this topic?`
            };
            
            return prompts[action] || `Tell me more about "${cardTitle}".`;
        }
        
        handleCardInterest(button) {
            const card = button.closest('.sffc-intelligence-card');
            button.classList.toggle('interested');
            
            if (button.classList.contains('interested')) {
                button.innerHTML = '❤️';
                button.title = 'Remove interest';
                card.classList.add('user-interested');
            } else {
                button.innerHTML = '♡';
                button.title = 'Show interest';
                card.classList.remove('user-interested');
            }
        }
        
        handleCardDismiss(button) {
            const card = button.closest('.sffc-intelligence-card');
            card.style.opacity = '0.5';
            card.style.pointerEvents = 'none';
            
            setTimeout(() => {
                card.remove();
            }, 300);
        }
        
        hookIntoZENAEvents() {
            // Listen for ZENA's filter events
            document.addEventListener('sennaJobsLoaded', () => {
                console.log('🎯 Custom Intelligence: ZENA jobs loaded event detected');
                this.updateCustomFilterCounts();
            });
            
            document.addEventListener('peFilterChanged', (e) => {
                console.log('🎯 Custom Intelligence: PE filter changed:', e.detail);
                // Reset custom filters when ZENA filters change
                if (e.detail.filterType !== 'custom') {
                    this.resetCustomFilters();
                }
            });
        }
        
        updateCustomFilterCounts() {
            // Update counts for all custom filters
            Object.keys(this.filterTypes).forEach(filterType => {
                // For now, show static counts - could be enhanced with real-time data
                const count = Math.floor(Math.random() * 20) + 5;
                this.updateFilterCount(filterType, count);
            });
        }
        
        resetCustomFilters() {
            document.querySelectorAll('.custom-intelligence-filter').forEach(card => {
                card.classList.remove('active', 'selected');
            });
            
            const container = this.getCardsContainer();
            if (container) {
                container.classList.remove('custom-intelligence-active');
            }
            
            this.activeFilter = null;
        }
        
        createStandaloneSystem() {
            console.log('🎯 Custom Intelligence: Creating standalone filter system');
            
            // Create a minimal container if ZENA's system isn't available
            const container = document.createElement('div');
            container.id = 'custom-intelligence-standalone';
            container.className = 'custom-intelligence-standalone-container';
            container.innerHTML = `
                <div class="standalone-header">
                    <h3>Custom Intelligence Filters</h3>
                </div>
                <div class="standalone-filters">
                    ${this.generateCustomFilterCards()}
                </div>
                <div class="standalone-cards-container">
                    <div class="welcome-message">
                        Select a filter above to view intelligence cards
                    </div>
                </div>
            `;
            
            // Insert before main container or at end of body
            const mainContainer = document.querySelector('.sffc-main-container, body');
            if (mainContainer === document.body) {
                document.body.appendChild(container);
            } else {
                mainContainer.parentNode.insertBefore(container, mainContainer);
            }
            
            this.bindCustomFilterEvents(container);
        }
        
        // Public API
        getActiveFilter() {
            return this.activeFilter;
        }
        
        loadFilter(filterType) {
            if (this.filterTypes[filterType]) {
                this.loadCustomIntelligenceCards(filterType);
                return true;
            }
            return false;
        }
        
        refreshCounts() {
            this.updateCustomFilterCounts();
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Give ZENA's system a moment to initialize
        setTimeout(() => {
            window.CustomIntelligenceFilters = new CustomIntelligenceFilters();
        }, 1000);
    });
    
    // Expose for debugging
    window.CustomIntelligenceFilters = CustomIntelligenceFilters;
    
})(jQuery);