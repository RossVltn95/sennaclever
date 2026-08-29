/**
 * Market Mode Frontend Integration
 * Connects Market Analysis Mode to the chat interface
 * 
 * @package SkillFarmFinance
 * @since 2.0.0
 */

(function($) {
    'use strict';
    
    // Compatibility layer for localization
    if (typeof sffc_ajax !== 'undefined' && typeof sffc_frontend === 'undefined') {
        // Map old variable to new standard
        window.sffc_frontend = sffc_ajax;
    }
    
    /**
     * Market Mode Controller
     */
    window.SFFC_MarketMode = {
        
        // Current mode state
        isActive: false,
        currentContext: {},
        marketData: null,
        lastUpdate: null,
        visualLibrary: null,
        
        /**
         * Initialize Market Mode
         */
        init: function() {
            this.bindEvents();
            this.loadVisualLibrary();
            this.checkMarketStatus();
            this.initRealTimeUpdates();
        },
        
        /**
         * Bind UI events
         */
        bindEvents: function() {
            // Mode toggle
            $(document).on('click', '.sffc-toggle-market-mode', this.toggleMarketMode.bind(this));
            
            // Market-specific queries
            $(document).on('sffc:query:submit', this.interceptMarketQuery.bind(this));
            
            // Visual interactions
            $(document).on('click', '.sffc-market-visual', this.handleVisualInteraction.bind(this));
            
            // Quick market actions
            $(document).on('click', '.sffc-market-quick-action', this.handleQuickAction.bind(this));
        },
        
        /**
         * Load visual component library
         */
        loadVisualLibrary: function() {
            // Use the complete visual renderer instead of missing SFFC_MarketVisuals
            if (typeof SFFC_VisualRenderer !== 'undefined') {
                this.visualLibrary = SFFC_VisualRenderer;
            } else if (typeof window.SFFC_VisualRenderer !== 'undefined') {
                this.visualLibrary = window.SFFC_VisualRenderer;
            }
        },
        
        /**
         * Toggle Market Mode on/off
         */
        toggleMarketMode: function() {
            this.isActive = !this.isActive;
            
            if (this.isActive) {
                this.activateMarketMode();
            } else {
                this.deactivateMarketMode();
            }
        },
        
        /**
         * Activate Market Mode
         */
        activateMarketMode: function() {
            // Update UI
            $('body').addClass('sffc-market-mode-active');
            $('.sffc-chat-container').addClass('market-mode');
            
            // Load market dashboard
            this.loadMarketDashboard();
            
            // Start real-time updates
            this.startRealTimeUpdates();
            
            // Show market greeting
            this.showMarketGreeting();
            
            // Update context
            this.currentContext.mode = 'market';
            
            // Notify user
            this.displayNotification('Market Analysis Mode activated', 'success');
        },
        
        /**
         * Deactivate Market Mode
         */
        deactivateMarketMode: function() {
            // Update UI
            $('body').removeClass('sffc-market-mode-active');
            $('.sffc-chat-container').removeClass('market-mode');
            
            // Stop real-time updates
            this.stopRealTimeUpdates();
            
            // Clear market dashboard
            $('.sffc-market-dashboard').fadeOut();
            
            // Update context
            this.currentContext.mode = 'career';
            
            // Notify user
            this.displayNotification('Switched to Career Mode', 'info');
        },
        
        /**
         * Load market dashboard
         */
        loadMarketDashboard: function() {
            const dashboardHtml = `
                <div class="sffc-market-dashboard">
                    <div class="market-header">
                        <h3>Market Intelligence</h3>
                        <span class="last-update">Updating...</span>
                    </div>
                    
                    <div class="market-indicators">
                        <div class="indicator" data-type="volatility">
                            <span class="label">VIX</span>
                            <span class="value">--</span>
                            <span class="change">--</span>
                        </div>
                        <div class="indicator" data-type="sentiment">
                            <span class="label">Sentiment</span>
                            <span class="value">--</span>
                            <span class="trend">--</span>
                        </div>
                        <div class="indicator" data-type="momentum">
                            <span class="label">Momentum</span>
                            <span class="value">--</span>
                            <span class="direction">--</span>
                        </div>
                    </div>
                    
                    <div class="trending-topics">
                        <h4>Trending Now</h4>
                        <div class="topics-list"></div>
                    </div>
                    
                    <div class="quick-actions">
                        <button class="sffc-market-quick-action" data-action="why-analysis">
                            Why is this happening?
                        </button>
                        <button class="sffc-market-quick-action" data-action="opportunities">
                            Show opportunities
                        </button>
                        <button class="sffc-market-quick-action" data-action="education">
                            Teach me something
                        </button>
                    </div>
                </div>
            `;
            
            // Insert dashboard
            $('.sffc-sidebar').prepend(dashboardHtml);
            
            // Animate in
            $('.sffc-market-dashboard').hide().fadeIn(500);
            
            // Load initial data
            this.refreshMarketData();
        },
        
        /**
         * Show market-specific greeting
         */
        showMarketGreeting: function() {
            const hour = new Date().getHours();
            let greeting;
            
            if (hour < 12) {
                greeting = "Good morning! Let me show you what moved in the markets overnight and why it matters for your career.";
            } else if (hour < 17) {
                greeting = "Good afternoon! The market's showing its hand. Let me explain what these moves really mean.";
            } else {
                greeting = "Good evening! Let's make sense of today's market action and what it sets up for tomorrow.";
            }
            
            // Display greeting
            this.displayAssistantMessage(greeting, {
                type: 'market-greeting',
                animated: true
            });
        },
        
        /**
         * Intercept and handle market queries
         */
        interceptMarketQuery: function(event, data) {
            if (!this.isActive) return;
            
            // Analyze query for market intent
            const queryLower = data.query.toLowerCase();
            const isMarketQuery = this.isMarketRelatedQuery(queryLower);
            
            if (isMarketQuery) {
                event.preventDefault();
                this.handleMarketQuery(data.query);
            }
        },
        
        /**
         * Check if query is market-related
         */
        isMarketRelatedQuery: function(query) {
            const marketKeywords = [
                'market', 'stock', 'trade', 'price', 'rally', 'sell',
                'why', 'happening', 'movement', 'volatility', 'opportunity',
                'compare', 'versus', 'analysis', 'trend', 'signal'
            ];
            
            return marketKeywords.some(keyword => query.includes(keyword));
        },
        
        /**
         * Handle market-specific query
         */
        handleMarketQuery: function(query) {
            // Show typing indicator
            this.showTypingIndicator();
            
            // Prepare context
            const context = {
                mode: 'market',
                user_profile: this.getUserProfile(),
                recent_topics: this.getRecentTopics(),
                market_conditions: this.marketData
            };
            
            // Send to market analysis endpoint
            $.ajax({
                url: (typeof sffc_frontend !== 'undefined' ? sffc_frontend.ajax_url : '/wp-admin/admin-ajax.php'),
                type: 'POST',
                data: {
                    action: 'sffc_market_analysis',
                    query: query,
                    context: JSON.stringify(context),
                    nonce: (typeof sffc_frontend !== 'undefined' ? sffc_frontend.nonce : '')
                },
                success: this.handleMarketResponse.bind(this),
                error: this.handleMarketError.bind(this),
                complete: this.hideTypingIndicator.bind(this)
            });
        },
        
        /**
         * Handle market analysis response
         */
        handleMarketResponse: function(response) {
            if (response.success) {
                const data = response.data;
                
                // Display main message
                this.displayAssistantMessage(data.message, {
                    type: 'market-analysis',
                    metadata: data.metadata
                });
                
                // Render visual components
                if (data.visuals && data.visuals.length > 0) {
                    this.renderVisualComponents(data.visuals);
                }
                
                // Show follow-up suggestions
                if (data.metadata && data.metadata.follow_ups) {
                    this.showFollowUpSuggestions(data.metadata.follow_ups);
                }
                
                // Update context
                this.updateMarketContext(data);
            }
        },
        
        /**
         * Render visual components
         */
        renderVisualComponents: function(visuals) {
            visuals.forEach(visual => {
                // Use the main render method if available
                if (this.visualLibrary && this.visualLibrary.render) {
                    const visualHtml = this.visualLibrary.render(visual);
                    this.insertVisualComponent(visualHtml);
                } else {
                    // Fallback to individual renderers
                    const renderer = this.visualLibrary[`render${this.capitalize(visual.type)}`];
                    if (typeof renderer === 'function') {
                        const visualHtml = renderer.call(this.visualLibrary, visual.data || visual);
                        this.insertVisualComponent(visualHtml);
                    }
                }
            });
        },
        
        /**
         * Insert visual component into chat
         */
        insertVisualComponent: function(html) {
            const $visual = $('<div class="sffc-message sffc-visual-message"></div>').html(html);
            $('.sffc-messages-container').append($visual);
            
            // Animate in
            $visual.hide().fadeIn(300);
            
            // Scroll to bottom
            this.scrollToBottom();
        },
        
        /**
         * Initialize real-time updates
         */
        initRealTimeUpdates: function() {
            this.updateInterval = null;
            this.updateFrequency = 60000; // 1 minute
        },
        
        /**
         * Start real-time market updates
         */
        startRealTimeUpdates: function() {
            // Initial update
            this.refreshMarketData();
            
            // Set interval
            this.updateInterval = setInterval(() => {
                this.refreshMarketData();
            }, this.updateFrequency);
        },
        
        /**
         * Stop real-time updates
         */
        stopRealTimeUpdates: function() {
            if (this.updateInterval) {
                clearInterval(this.updateInterval);
                this.updateInterval = null;
            }
        },
        
        /**
         * Refresh market data
         */
        refreshMarketData: function() {
            $.ajax({
                url: (typeof sffc_frontend !== 'undefined' ? sffc_frontend.ajax_url : '/wp-admin/admin-ajax.php'),
                type: 'POST',
                data: {
                    action: 'sffc_get_market_data',
                    nonce: (typeof sffc_frontend !== 'undefined' ? sffc_frontend.nonce : '')
                },
                success: (response) => {
                    if (response.success) {
                        this.marketData = response.data;
                        this.updateMarketDashboard(response.data);
                        this.lastUpdate = new Date();
                    }
                }
            });
        },
        
        /**
         * Update market dashboard with new data
         */
        updateMarketDashboard: function(data) {
            // Update indicators
            if (data.indicators) {
                Object.keys(data.indicators).forEach(key => {
                    const indicator = data.indicators[key];
                    const $element = $(`.indicator[data-type="${key}"]`);
                    
                    $element.find('.value').text(indicator.value);
                    $element.find('.change, .trend, .direction').text(indicator.change);
                    
                    // Add color coding
                    if (indicator.direction === 'up') {
                        $element.addClass('positive').removeClass('negative');
                    } else if (indicator.direction === 'down') {
                        $element.addClass('negative').removeClass('positive');
                    }
                });
            }
            
            // Update trending topics
            if (data.trending) {
                const topicsHtml = data.trending.map(topic => `
                    <div class="topic-item" data-topic="${topic.id}">
                        <span class="topic-name">${topic.name}</span>
                        <span class="topic-heat">${topic.heat}</span>
                    </div>
                `).join('');
                
                $('.topics-list').html(topicsHtml);
            }
            
            // Update timestamp
            $('.last-update').text('Updated: ' + this.formatTime(this.lastUpdate));
        },
        
        /**
         * Handle quick market actions
         */
        handleQuickAction: function(event) {
            const action = $(event.currentTarget).data('action');
            
            switch(action) {
                case 'why-analysis':
                    this.requestWhyAnalysis();
                    break;
                case 'opportunities':
                    this.requestOpportunities();
                    break;
                case 'education':
                    this.requestEducation();
                    break;
            }
        },
        
        /**
         * Request WHY analysis for current market
         */
        requestWhyAnalysis: function() {
            const query = "Why is the market moving this way today?";
            this.handleMarketQuery(query);
        },
        
        /**
         * Request opportunities based on current market
         */
        requestOpportunities: function() {
            const query = "What opportunities do you see in the current market?";
            this.handleMarketQuery(query);
        },
        
        /**
         * Request educational content
         */
        requestEducation: function() {
            const query = "Teach me something important about today's market dynamics";
            this.handleMarketQuery(query);
        },
        
        /**
         * Show follow-up suggestions
         */
        showFollowUpSuggestions: function(suggestions) {
            const html = `
                <div class="sffc-follow-up-suggestions">
                    <p>Want to explore further?</p>
                    <div class="suggestions">
                        ${suggestions.map(s => `
                            <button class="suggestion-btn" data-query="${s}">
                                ${s}
                            </button>
                        `).join('')}
                    </div>
                </div>
            `;
            
            $('.sffc-messages-container').append(html);
            
            // Bind click events
            $('.suggestion-btn').on('click', (e) => {
                const query = $(e.currentTarget).data('query');
                this.handleMarketQuery(query);
                $(e.currentTarget).parent().parent().fadeOut();
            });
        },
        
        /**
         * Helper methods
         */
        displayAssistantMessage: function(message, options = {}) {
            // Implementation would connect to main chat interface
            const $message = $('<div class="sffc-message sffc-assistant-message"></div>');
            
            if (options.animated) {
                // Typing animation
                $message.addClass('typing');
                setTimeout(() => {
                    $message.removeClass('typing').html(message);
                }, 1000);
            } else {
                $message.html(message);
            }
            
            if (options.type) {
                $message.addClass(`message-${options.type}`);
            }
            
            $('.sffc-messages-container').append($message);
            this.scrollToBottom();
        },
        
        showTypingIndicator: function() {
            $('.sffc-typing-indicator').fadeIn();
        },
        
        hideTypingIndicator: function() {
            $('.sffc-typing-indicator').fadeOut();
        },
        
        scrollToBottom: function() {
            const container = $('.sffc-messages-container')[0];
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
        
        displayNotification: function(message, type) {
            // Simple notification
            const $notification = $(`<div class="sffc-notification ${type}">${message}</div>`);
            $('body').append($notification);
            
            $notification.fadeIn().delay(3000).fadeOut(() => {
                $notification.remove();
            });
        },
        
        formatTime: function(date) {
            return date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        capitalize: function(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        },
        
        getUserProfile: function() {
            // Get from session or storage
            return this.currentContext.user_profile || {};
        },
        
        getRecentTopics: function() {
            // Get from conversation history
            return this.currentContext.recent_topics || [];
        },
        
        updateMarketContext: function(data) {
            this.currentContext.last_analysis = data;
            this.currentContext.timestamp = new Date();
        },
        
        handleMarketError: function(xhr, status, error) {
            console.error('Market analysis error:', error);
            this.displayAssistantMessage(
                "I'm having trouble analyzing the markets right now. Please try again in a moment.",
                { type: 'error' }
            );
        },
        
        checkMarketStatus: function() {
            // Check if markets are open
            const now = new Date();
            const hour = now.getHours();
            const day = now.getDay();
            
            // Simple market hours check (NYSE: 9:30 AM - 4:00 PM ET, Mon-Fri)
            const isMarketHours = day > 0 && day < 6 && hour >= 9 && hour < 16;
            
            this.currentContext.market_open = isMarketHours;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if (typeof SFFC_MarketMode !== 'undefined') {
            SFFC_MarketMode.init();
        }
    });
    
})(jQuery);