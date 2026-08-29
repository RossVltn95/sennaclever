/**
 * Dynamic Post Type Filters Frontend
 * Integrates with ZENA's PE filter system to load real data from custom post types
 * Works exactly like M42's job filter system
 * 
 * @package SkillFarmFinance
 * @since 10.20.0
 */

(function($) {
    'use strict';
    
    class DynamicPostTypeFilters {
        constructor() {
            this.config = window.sffc_dynamic_filters || {};
            this.filterConfigs = this.config.filters || {};
            this.activeFilter = null;
            this.isLoading = false;
            
            console.log('🎯 Dynamic Post Type Filters: Initializing...', this.filterConfigs);
            
            this.init();
        }
        
        init() {
            // Wait for ZENA's PE filter system to be ready
            this.waitForZENASystem();
            
            // Listen for ZENA events
            document.addEventListener('peFilterContainerReady', () => {
                console.log('🎯 Dynamic Filters: PE container ready');
                this.integrateWithZENA();
            });
            
            // Check if container already exists
            setTimeout(() => {
                if (document.querySelector('#pe-filter-container')) {
                    this.integrateWithZENA();
                }
            }, 1000);
        }
        
        waitForZENASystem() {
            let attempts = 0;
            const maxAttempts = 20;
            
            const checkZENA = setInterval(() => {
                attempts++;
                
                // Look for ZENA's story filters system
                if (document.querySelector('.sffc-story-filters')) {
                    clearInterval(checkZENA);
                    console.log('🎯 Dynamic Filters: ZENA story filters detected');
                    setTimeout(() => this.integrateWithZENA(), 500);
                    return;
                }
                
                if (attempts >= maxAttempts) {
                    clearInterval(checkZENA);
                    console.log('🎯 Dynamic Filters: No story filters found, standalone system disabled');
                    // Standalone system disabled - container was rendering on every page
                    // this.createStandaloneSystem();
                }
            }, 250);
        }
        
        integrateWithZENA() {
            console.log('🎯 Dynamic Filters: Integrating with ZENA story filters...');
            
            // Find ZENA's story filters container
            const storyFiltersContainer = document.querySelector('.sffc-story-filters');
            if (!storyFiltersContainer) {
                console.warn('🎯 Dynamic Filters: No sffc-story-filters container found');
                return;
            }
            
            // Add our custom post type filters to story filters
            this.addPostTypeFiltersToStoryFilters(storyFiltersContainer);
            
            // Hook into story filter events
            this.hookIntoStoryFilterEvents();
            
            console.log('🎯 Dynamic Filters: Successfully integrated with ZENA story filters');
        }
        
        addPostTypeFiltersToStoryFilters(storyFiltersContainer) {
            console.log('🎯 Dynamic Filters: Adding custom filters to story filters...');
            
            // Generate filter items from our configurations
            // Skip existing ZENA filters to avoid duplication
            Object.entries(this.filterConfigs)
                .filter(([key, config]) => config.active && !config.existing_filter)
                .sort((a, b) => (a[1].priority || 999) - (b[1].priority || 999))
                .forEach(([key, config]) => {
                    const filterItem = this.createStoryFilterItem(key, config);
                    storyFiltersContainer.appendChild(filterItem);
                });
            
            console.log('🎯 Dynamic Filters: Added', Object.keys(this.filterConfigs).length, 'custom filters');
        }
        
        createStoryFilterItem(key, config) {
            const filterItem = document.createElement('div');
            filterItem.className = 'sffc-filter-item';
            filterItem.setAttribute('data-filter', key);
            filterItem.setAttribute('data-post-type', config.post_type);
            filterItem.setAttribute('data-filter-key', key);
            
            // Create the filter structure matching ZENA's pattern
            const iconHtml = config.icon && config.icon.includes('<svg') 
                ? config.icon 
                : `<span class="sffc-filter-icon-text">${config.icon || 'DATA'}</span>`;
            
            filterItem.innerHTML = `
                <div class="sffc-filter-circle">
                    <span class="sffc-filter-icon">${iconHtml}</span>
                    <span class="sffc-filter-badge">0</span>
                </div>
                <span class="sffc-filter-label">${config.label.toUpperCase()}</span>
            `;
            
            // Bind click event
            filterItem.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleStoryFilterClick(key, config, filterItem);
            });
            
            return filterItem;
        }
        
        generatePostTypeFilterCards() {
            if (Object.keys(this.filterConfigs).length === 0) {
                return '<div class="no-post-type-filters">No post type filters configured</div>';
            }
            
            return Object.entries(this.filterConfigs)
                .filter(([key, config]) => config.active)
                .sort((a, b) => (a[1].priority || 999) - (b[1].priority || 999))
                .map(([key, config]) => this.createPostTypeFilterCard(key, config))
                .join('');
        }
        
        createPostTypeFilterCard(key, config) {
            return `
                <div class="pe-filter-card post-type-filter" 
                     data-filter-key="${key}" 
                     data-post-type="${config.post_type}"
                     data-filter-category="post_type"
                     style="border-left: 4px solid ${config.color || '#3b82f6'}">
                    <div class="filter-icon">${config.icon || '📊'}</div>
                    <div class="filter-content">
                        <div class="filter-title">${config.label}</div>
                        <div class="filter-description">From ${config.post_type} posts</div>
                    </div>
                    <div class="filter-status">
                        <span class="filter-count" data-filter="${key}">-</span>
                    </div>
                </div>
            `;
        }
        
        handleStoryFilterClick(key, config, filterItem) {
            if (this.isLoading) {
                console.log('🎯 Dynamic Filters: Already loading, ignoring click');
                return;
            }
            
            console.log('🎯 Dynamic Filters: Story filter clicked:', key, config.label);
            
            // Update active state - remove from all story filters and set on clicked one
            document.querySelectorAll('.sffc-filter-item').forEach(item => {
                item.classList.remove('active');
            });
            filterItem.classList.add('active');
            
            // Load posts from the custom post type
            this.loadPostTypeDataForStoryFilter(key, config);
        }
        
        handlePostTypeFilterClick(filterKey, card) {
            if (this.isLoading) {
                console.log('🎯 Dynamic Filters: Already loading, ignoring click');
                return;
            }
            
            console.log('🎯 Dynamic Filters: Post type filter clicked:', filterKey);
            
            // Update active state
            this.updateActiveFilterState(card);
            
            // Load posts from custom post type
            this.loadPostTypeData(filterKey);
        }
        
        updateActiveFilterState(activeCard) {
            // Remove active state from all filter cards
            document.querySelectorAll('.pe-filter-card').forEach(card => {
                card.classList.remove('active', 'selected');
            });
            
            // Add active state to clicked card
            activeCard.classList.add('active', 'selected');
            
            this.activeFilter = activeCard.dataset.filterKey;
        }
        
        loadPostTypeDataForStoryFilter(filterKey, config) {
            this.isLoading = true;
            
            console.log('🎯 Dynamic Filters: Loading data for story filter:', filterKey, config.post_type);
            
            // Show loading state in intelligence cards container
            this.showIntelligenceCardsLoading();
            
            // Make AJAX request to get posts from custom post type
            const data = {
                action: 'sffc_dynamic_post_filter',
                filter_key: filterKey,
                limit: 12,
                nonce: this.config.nonce
            };
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: data,
                success: (response) => {
                    console.log('🎯 Dynamic Filters: AJAX response for story filter:', response);
                    
                    if (response.success) {
                        this.displayIntelligenceCards(response.data.html, filterKey);
                        this.updateStoryFilterBadge(filterKey, response.data.count);
                    } else {
                        this.showIntelligenceCardsError(response.data || 'Failed to load post data');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🎯 Dynamic Filters: AJAX error:', error);
                    this.showIntelligenceCardsError('Network error loading post data');
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
                    <div class="post-type-loading">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Loading posts...</div>
                    </div>
                `;
            }
        }
        
        displayPostTypeCards(html, filterKey) {
            const container = this.getCardsContainer();
            if (!container) {
                console.error('🎯 Dynamic Filters: No cards container found');
                return;
            }
            
            container.innerHTML = html;
            
            // Add specific class for post type cards
            container.classList.add('post-type-cards-active');
            
            // Trigger event for other systems
            document.dispatchEvent(new CustomEvent('postTypeCardsLoaded', {
                detail: { 
                    filterKey: filterKey,
                    container: container,
                    cardCount: container.querySelectorAll('.sffc-intelligence-card').length
                }
            }));
            
            // Initialize card interactions
            this.initializePostTypeCardInteractions(container);
            
            console.log('🎯 Dynamic Filters: Post type cards displayed successfully');
        }
        
        showErrorState(message) {
            const container = this.getCardsContainer();
            if (container) {
                container.innerHTML = `
                    <div class="post-type-error">
                        <div class="error-icon">⚠️</div>
                        <div class="error-message">${message}</div>
                        <div class="error-help">
                            <p>Make sure you have:</p>
                            <ul>
                                <li>Created posts in the custom post type</li>
                                <li>Set the correct field mappings</li>
                                <li>Published the posts</li>
                            </ul>
                        </div>
                        <button class="retry-button" onclick="location.reload()">Retry</button>
                    </div>
                `;
            }
        }
        
        showIntelligenceCardsLoading() {
            const container = this.getIntelligenceCardsContainer();
            if (container) {
                container.innerHTML = `
                    <div class="sffc-intelligence-loading">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Loading intelligence cards...</div>
                    </div>
                `;
            }
        }
        
        displayIntelligenceCards(html, filterKey) {
            const container = this.getIntelligenceCardsContainer();
            if (!container) {
                console.error('🎯 Dynamic Filters: No intelligence cards container found');
                return;
            }
            
            // Replace the container content with the new intelligence cards
            container.innerHTML = html;
            
            console.log('🎯 Dynamic Filters: Intelligence cards displayed for filter:', filterKey);
            
            // Initialize card interactions
            this.initializeIntelligenceCardInteractions(container);
        }
        
        showIntelligenceCardsError(message) {
            const container = this.getIntelligenceCardsContainer();
            if (container) {
                container.innerHTML = `
                    <div class="sffc-intelligence-error">
                        <div class="error-icon">⚠️</div>
                        <div class="error-message">${message}</div>
                        <div class="error-help">
                            <p>Make sure you have:</p>
                            <ul>
                                <li>Created posts in the custom post type</li>
                                <li>Set the correct field mappings</li>
                                <li>Published the posts</li>
                            </ul>
                        </div>
                    </div>
                `;
            }
        }
        
        updateStoryFilterBadge(filterKey, count) {
            // Try to find the filter badge for both new filters (with data-filter-key) and existing filters (with data-filter)
            let filterBadge = document.querySelector(`.sffc-filter-item[data-filter-key="${filterKey}"] .sffc-filter-badge`);
            
            if (!filterBadge) {
                // Try existing ZENA filters
                filterBadge = document.querySelector(`.sffc-filter-item[data-filter="${filterKey}"] .sffc-filter-badge`);
            }
            
            if (filterBadge) {
                filterBadge.textContent = count;
                console.log('🎯 Dynamic Filters: Updated badge for', filterKey, 'to', count);
            } else {
                console.log('🎯 Dynamic Filters: No badge found for filter:', filterKey);
            }
        }
        
        getIntelligenceCardsContainer() {
            // Look for the specific intelligence cards container in ZENA
            return document.querySelector(
                '.sffc-intelligence-cards, .sffc-cards-grid, .intelligence-cards-container, ' +
                '.senna-conversation-area .cards-container, #senna-conversation .cards-container'
            );
        }
        
        updateFilterCount(filterKey, count) {
            const countElement = document.querySelector(`[data-filter="${filterKey}"] .filter-count`);
            if (countElement) {
                countElement.textContent = count;
            }
        }
        
        initializeIntelligenceCardInteractions(container) {
            // Initialize action buttons for intelligence cards
            const actionButtons = container.querySelectorAll('.sffc-action-btn');
            actionButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleIntelligenceCardAction(button);
                });
            });
            
            // Initialize interest/dismiss buttons
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
        
        handleIntelligenceCardAction(button) {
            const action = button.dataset.action;
            const postId = button.dataset.postId;
            const card = button.closest('.sffc-intelligence-card');
            const postType = card.dataset.postType;
            
            console.log('🎯 Dynamic Filters: Intelligence card action:', action, postId, postType);
            
            // Integrate with MENA Careers chat if available
            if (window.SennaChat && typeof window.SennaChat.send === 'function') {
                const cardTitle = card.querySelector('.sffc-card-headline')?.textContent || 'Post';
                const prompt = this.generatePostActionPrompt(action, cardTitle, postId, postType);
                window.SennaChat.send(prompt);
            } else if (action === 'view' && postId) {
                // Fallback: try to view the post
                const postUrl = this.getPostViewUrl(postId, postType);
                if (postUrl) {
                    window.open(postUrl, '_blank');
                }
            } else {
                console.log('🎯 Dynamic Filters: No action handler available');
            }
        }
        
        generatePostActionPrompt(action, cardTitle, postId, postType) {
            const prompts = {
                view: `Tell me more about "${cardTitle}" (ID: ${postId}) from ${postType}.`,
                analyze: `Please provide a detailed analysis of "${cardTitle}". What are the key insights and strategic implications?`,
                track: `I want to track developments related to "${cardTitle}". Can you help me monitor this topic?`,
                compare: `Can you compare "${cardTitle}" with similar items in ${postType}?`
            };
            
            return prompts[action] || `Tell me about "${cardTitle}" from ${postType}.`;
        }
        
        getPostViewUrl(postId, postType) {
            // Try to construct post URL
            if (postId) {
                return `${window.location.origin}/?p=${postId}`;
            }
            return null;
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
        
        hookIntoStoryFilterEvents() {
            // Listen for clicks on existing story filter items and handle them if they have configurations
            document.querySelectorAll('.sffc-filter-item:not([data-filter-key])').forEach(filterItem => {
                const filterKey = filterItem.dataset.filter;
                
                // If this is a filter we have configuration for, handle it with our system
                if (this.filterConfigs[filterKey] && this.filterConfigs[filterKey].existing_filter) {
                    filterItem.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('🎯 Dynamic Filters: Existing ZENA filter clicked with our config:', filterKey);
                        this.handleExistingZENAFilterClick(filterKey, this.filterConfigs[filterKey], filterItem);
                    });
                } else {
                    // For filters we don't manage, just reset our custom states
                    filterItem.addEventListener('click', () => {
                        console.log('🎯 Dynamic Filters: Built-in story filter clicked, resetting custom filters');
                        this.resetCustomFilterStates();
                    });
                }
            });
            
            // Update filter counts when the page loads
            setTimeout(() => {
                this.updateAllFilterCounts();
            }, 2000);
        }
        
        handleExistingZENAFilterClick(filterKey, config, filterItem) {
            if (this.isLoading) {
                console.log('🎯 Dynamic Filters: Already loading, ignoring click');
                return;
            }
            
            console.log('🎯 Dynamic Filters: Handling existing ZENA filter:', filterKey, config.label);
            
            // Update active state - remove from all story filters and set on clicked one
            document.querySelectorAll('.sffc-filter-item').forEach(item => {
                item.classList.remove('active');
            });
            filterItem.classList.add('active');
            
            // Load posts from the custom post type using our system
            this.loadPostTypeDataForStoryFilter(filterKey, config);
        }
        
        resetCustomFilterStates() {
            // Remove active state from all custom filter items
            document.querySelectorAll('.sffc-filter-item[data-filter-key]').forEach(filterItem => {
                filterItem.classList.remove('active');
            });
            
            this.activeFilter = null;
        }
        
        updateAllFilterCounts() {
            // Get counts for all post type filters (both existing and new)
            Object.keys(this.filterConfigs).forEach(filterKey => {
                const config = this.filterConfigs[filterKey];
                if (config.post_type && config.active) {
                    this.getPostTypeCount(filterKey, config.post_type);
                }
            });
        }
        
        getPostTypeCount(filterKey, postType) {
            // Make AJAX call to get the count of posts for this filter
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_get_post_type_count',
                    post_type: postType,
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStoryFilterBadge(filterKey, response.data.count);
                    }
                },
                error: () => {
                    this.updateStoryFilterBadge(filterKey, '?');
                }
            });
        }
        
        createStandaloneSystem() {
            console.log('🎯 Dynamic Filters: Creating standalone system');
            
            // Create minimal standalone container
            const container = document.createElement('div');
            container.id = 'dynamic-post-filters-standalone';
            container.className = 'dynamic-post-filters-standalone-container';
            container.innerHTML = `
                <div class="standalone-header">
                    <h3>Post Type Filters</h3>
                    <p>Real data from your custom post types</p>
                </div>
                <div class="standalone-filters">
                    ${this.generatePostTypeFilterCards()}
                </div>
                <div class="standalone-cards-container">
                    <div class="welcome-message">
                        Select a filter above to view posts from your custom post types
                    </div>
                </div>
            `;
            
            // Insert into page
            const mainContainer = document.querySelector('.sffc-main-container, body');
            if (mainContainer === document.body) {
                document.body.appendChild(container);
            } else {
                mainContainer.parentNode.insertBefore(container, mainContainer);
            }
            
            this.bindPostTypeFilterEvents(container);
        }
        
        // Public API
        getActiveFilter() {
            return this.activeFilter;
        }
        
        loadFilter(filterKey) {
            if (this.filterConfigs[filterKey]) {
                this.loadPostTypeData(filterKey);
                return true;
            }
            return false;
        }
        
        refreshCounts() {
            this.updatePostTypeFilterCounts();
        }
        
        getFilterConfig(filterKey) {
            return this.filterConfigs[filterKey];
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        // Give ZENA's system time to initialize
        setTimeout(() => {
            window.DynamicPostTypeFilters = new DynamicPostTypeFilters();
        }, 1200);
    });
    
    // Expose for debugging
    window.DynamicPostTypeFilters = DynamicPostTypeFilters;
    
})(jQuery);