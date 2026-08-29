/**
 * PE Intelligence Cards JavaScript
 * Handles interactions, filtering, and dynamic loading
 */

(function($) {
    'use strict';

    // Main controller
    const PEIntelligence = {
        
        // Configuration
        config: {
            ajaxUrl: sffc_intelligence.ajax_url,
            nonce: sffc_intelligence.nonce,
            refreshInterval: sffc_intelligence.refresh_interval || 60000,
            currency: sffc_intelligence.currency || 'GBP',
            isMember: !!sffc_intelligence.is_member,
            membership: sffc_intelligence.membership_cta || {}
        },
        
        // State
        state: {
            currentFilter: 'all',
            currentOffset: 0,
            isLoading: false,
            charts: {},
            autoRefresh: null,
            pageSize: 12,
            membershipModalOpen: false,
            sidebarOpen: true,
            isReplacingCard: false,
            interestedCardIds: []
        },
        
        // Initialize
        init: function() {
            this.cacheDomReferences();
            const $activeFilter = $('.sffc-filter-item.active').first();
            if ($activeFilter.length) {
                this.state.currentFilter = $activeFilter.data('filter');
            }
            this.state.pageSize = this.getPageSize();
            this.bindEvents();
            this.bindMembershipEvents();
            this.initializeSidebar();
            this.toggleJobFilterPanel();
            this.initCharts();
            this.startAutoRefresh();
            this.initCompanyProfiles();
            this.restoreInterestState();
            this.updateDashboardMode(this.state.currentFilter);
        },

        // Cache frequently used DOM nodes
        cacheDomReferences: function() {
            this.$membershipModal = $('#sffcMembershipModal');
            this.$sidebar = $('[data-filter-sidebar]');
            this.$sidebarToggles = $('[data-filter-sidebar-toggle]');
            this.$sidebarBackdrop = $('.sffc-sidebar-backdrop');
        },

        // Bind events
        bindEvents: function() {
            // Filter clicks
            $(document).on('click', '.sffc-filter-item', this.handleFilterClick.bind(this));
            
            // Load more
            $(document).on('click', '.sffc-load-more-btn', this.loadMoreCards.bind(this));
            
            // Company card clicks
            $(document).on('click', '.sffc-company-card', this.handleCompanyClick.bind(this));
            $(document).on('click', '.sffc-company-trigger', this.handleCompanyTrigger.bind(this));
            
            // Card action buttons
            $(document).on('click', '.sffc-action-btn', this.handleCardAction.bind(this));
            $(document).on('click', '.sffc-card-icon-btn[data-card-action="interest"]', this.handleInterestToggle.bind(this));
            $(document).on('click', '.sffc-card-icon-btn[data-card-action="dismiss"]', this.handleCardDismiss.bind(this));

            // Sidebar close
            $(document).on('click', '.sffc-sidebar-close', this.closeSidebar.bind(this));

            // Click outside sidebar to close
            $(document).on('click', function(e) {
                if ($(e.target).hasClass('sffc-company-sidebar')) {
                    this.closeSidebar();
                }
            }.bind(this));

            // Job search interactions
            $(document).on('click', '.sffc-job-search-btn', this.handleJobSearch.bind(this));
            $(document).on('click', '.sffc-job-clear-btn', this.clearJobFilters.bind(this));
            $(document).on('click', '.sffc-job-choice', this.handleJobChoiceClick.bind(this));
            $(document).on('click', '[data-filter-sidebar-toggle]', this.handleSidebarToggle.bind(this));
        },

        // Bind membership modal events
        bindMembershipEvents: function() {
            if (!this.$membershipModal || !this.$membershipModal.length) {
                return;
            }

            this.$membershipModal.on('click', '[data-membership-close]', function() {
                this.hideMembershipPrompt();
            }.bind(this));

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && this.state.membershipModalOpen) {
                    this.hideMembershipPrompt();
                }
            }.bind(this));
        },

        // Initialise sidebar state and responsive behaviour
        initializeSidebar: function() {
            if (!this.$sidebar || !this.$sidebar.length) {
                return;
            }

            if (!$('.sffc-sidebar-backdrop').length) {
                $('body').append('<div class="sffc-sidebar-backdrop" data-filter-sidebar-toggle aria-hidden="true"></div>');
            }

            this.$sidebarBackdrop = $('.sffc-sidebar-backdrop');
            this.$sidebarBackdrop.attr('aria-hidden', 'true');

            const isDesktop = window.matchMedia('(min-width: 1025px)').matches;
            this.setSidebarState(isDesktop);

            $(window).on('resize', this.handleResize.bind(this));

            $(document).on('keydown.sffcSidebar', function(e) {
                if (e.key === 'Escape' && this.state.sidebarOpen && !window.matchMedia('(min-width: 1025px)').matches) {
                    this.setSidebarState(false);
                }
            }.bind(this));
        },

        handleResize: function() {
            const isDesktop = window.matchMedia('(min-width: 1025px)').matches;
            if (isDesktop) {
                this.setSidebarState(true);
            } else {
                this.setSidebarState(this.state.sidebarOpen);
            }
        },

        handleSidebarToggle: function(e) {
            e.preventDefault();
            this.setSidebarState(!this.state.sidebarOpen);
        },

        setSidebarState: function(open) {
            if (!this.$sidebar || !this.$sidebar.length) {
                return;
            }

            const isDesktop = window.matchMedia('(min-width: 1025px)').matches;

            if (isDesktop) {
                this.state.sidebarOpen = true;
                this.$sidebar.addClass('is-open');
                if (this.$sidebarToggles && this.$sidebarToggles.length) {
                    this.$sidebarToggles.attr('aria-expanded', 'true');
                }
                if (this.$sidebarBackdrop && this.$sidebarBackdrop.length) {
                    this.$sidebarBackdrop.removeClass('is-visible').attr('aria-hidden', 'true');
                }
                $('body').removeClass('sffc-sidebar-open');
                return;
            }

            this.state.sidebarOpen = open;
            this.$sidebar.toggleClass('is-open', open);

            if (this.$sidebarToggles && this.$sidebarToggles.length) {
                this.$sidebarToggles.attr('aria-expanded', open ? 'true' : 'false');
            }

            const showBackdrop = !!open;
            if (this.$sidebarBackdrop && this.$sidebarBackdrop.length) {
                this.$sidebarBackdrop.toggleClass('is-visible', showBackdrop).attr('aria-hidden', showBackdrop ? 'false' : 'true');
            }

            $('body').toggleClass('sffc-sidebar-open', showBackdrop);
        },

        // Handle filter click
        handleFilterClick: function(e) {
            e.preventDefault();
            const $filter = $(e.currentTarget);
            const filterType = $filter.data('filter');
            
            if (this.state.currentFilter === filterType) return;
            
            // Update UI
            $('.sffc-filter-item').removeClass('active');
            $filter.addClass('active');
            
            // Update state
            this.state.currentFilter = filterType;
            this.state.currentOffset = 0;
            this.toggleJobFilterPanel(filterType === 'jobs');
            this.updateDashboardMode(filterType);

            if (filterType === 'jobs' && !this.state.sidebarOpen) {
                this.setSidebarState(true);
            }

            // Load filtered cards
            this.loadFilteredCards();
        },

        // Load filtered cards
        loadFilteredCards: function() {
            if (this.state.isLoading) return;
            
            this.state.isLoading = true;
            this.state.currentOffset = 0;
            const $container = $('.sffc-cards-grid');
            const limit = this.state.pageSize;
            const filters = this.collectFilters();
            
            // Show loading state
            $container.addClass('sffc-loading');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_filter_cards',
                    filters: filters,
                    limit: limit,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Fade out and replace cards
                        $container.fadeOut(200, function() {
                            $container.html(response.data.html);
                            $container.fadeIn(300);
                            
                            // Reinitialize charts for new cards
                            this.initCharts();
                            this.applyInterestState();
                        }.bind(this));
                        
                        // Update load more button
                        this.updateLoadMoreButton(!!response.data.has_more);
                    }
                }.bind(this),
                error: function() {
                    console.error('Error loading filtered cards');
                },
                complete: function() {
                    this.state.isLoading = false;
                    $container.removeClass('sffc-loading');
                }.bind(this)
            });
        },

        // Load more cards
        loadMoreCards: function(e) {
            e.preventDefault();
            
            if (this.state.isLoading) return;
            
            this.state.isLoading = true;
            const $btn = $(e.currentTarget);
            const $container = $('.sffc-cards-grid');
            const limit = this.state.pageSize;
            const nextOffset = this.state.currentOffset + limit;
            const filters = this.collectFilters();
            
            // Show loading state
            $btn.text('Loading...').prop('disabled', true);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_intelligence_cards',
                    offset: nextOffset,
                    limit: limit,
                    filters: filters,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Append new cards with animation
                        const $newCards = $(response.data.html);
                        $newCards.hide();
                        $container.append($newCards);
                        $newCards.fadeIn(300);
                        
                        // Reinitialize charts for new cards
                        this.initCharts();
                        this.applyInterestState($newCards);
                        
                        // Update button state
                        this.state.currentOffset = nextOffset;
                        this.updateLoadMoreButton(!!response.data.has_more);
                    }
                }.bind(this),
                error: function() {
                    console.error('Error loading more cards');
                },
                complete: function() {
                    this.state.isLoading = false;
                    $btn.text('Load More').prop('disabled', false);
                }.bind(this)
            });
        },

        // Handle choice button click in advanced filters
        handleJobChoiceClick: function(e) {
            e.preventDefault();
            const $choice = $(e.currentTarget);

            if (this.membershipRequired($choice)) {
                return;
            }

            $choice.toggleClass('is-active');
            this.loadFilteredCards();
        },

        // Gather active filters to submit via AJAX
        collectFilters: function() {
            const filters = {};
            const filterType = this.state.currentFilter || 'all';

            filters.type = filterType;

            if (filterType === 'jobs') {
                const selections = {};

                $('.sffc-job-choice.is-active').each(function() {
                    const $chip = $(this);
                    const target = $chip.data('filter-target');
                    const value = $chip.data('filter-value');

                    if (!target || !value) {
                        return;
                    }

                    if (!selections[target]) {
                        selections[target] = [];
                    }

                    selections[target].push(value);
                });

                Object.keys(selections).forEach(function(key) {
                    selections[key] = Array.from(new Set(selections[key]));
                });

                Object.assign(filters, selections);
            }

            return filters;
        },

        // Handle click on company badge inside cards
        handleCompanyTrigger: function(e) {
            e.preventDefault();
            const companyId = $(e.currentTarget).data('company-id');
            if (companyId) {
                this.loadCompanyProfile(companyId);
            }
        },

        // Toggle visibility of the job filter panel
        toggleJobFilterPanel: function() {
            const $panel = $('[data-job-filter-panel]');
            if ($panel.length) {
                $panel.addClass('is-visible');
            }
        },

        updateDashboardMode: function(filterType) {
            const $dashboard = $('.sffc-intelligence-dashboard');
            if (!$dashboard.length) {
                return;
            }

            $dashboard.removeClass('is-jobs-mode is-markets-mode');

            if (filterType === 'jobs') {
                $dashboard.addClass('is-jobs-mode');
            } else if (filterType === 'markets' || filterType === 'market') {
                $dashboard.addClass('is-markets-mode');
            }
        },

        // Determine the page size from DOM configuration
        getPageSize: function() {
            const $grid = $('.sffc-cards-grid');
            if (!$grid.length) {
                return 12;
            }
            const initial = parseInt($grid.data('initial-cards'), 10);
            return initial && initial > 0 ? initial : 12;
        },

        // Handle changes to job filters
        // Handle job search button click
        handleJobSearch: function() {
            if (this.membershipRequired($('.sffc-job-search-btn'))) {
                return;
            }

            if (this.state.currentFilter !== 'jobs') {
                const $jobsFilter = $('.sffc-filter-item[data-filter="jobs"]');
                if ($jobsFilter.length) {
                    $jobsFilter.trigger('click');
                }
                return;
            }

            this.loadFilteredCards();
        },

        // Clear job filters and refresh results
        clearJobFilters: function() {
            if (this.state.currentFilter === 'jobs') {
                $('.sffc-job-choice.is-active').removeClass('is-active');
                this.loadFilteredCards();
            }
        },

        // Check if membership is required and prompt when locked
        membershipRequired: function($trigger) {
            if (this.config.isMember) {
                return false;
            }

            this.showMembershipPrompt($trigger);
            return true;
        },

        // Show membership modal prompt
        showMembershipPrompt: function($trigger) {
            if (!this.$membershipModal || !this.$membershipModal.length) {
                return;
            }

            this.$membershipModal.addClass('is-visible');
            this.$membershipModal.attr('aria-hidden', 'false');
            $('body').addClass('sffc-modal-open');
            this.state.membershipModalOpen = true;

            const $cta = this.$membershipModal.find('.sffc-membership-join');
            if ($cta.length) {
                $cta.trigger('focus');
            }

            if ($trigger && $trigger.length) {
                $trigger.addClass('is-locked');
                setTimeout(function() {
                    $trigger.removeClass('is-locked');
                }, 600);
            }
        },

        // Hide membership modal
        hideMembershipPrompt: function() {
            if (!this.$membershipModal || !this.$membershipModal.length) {
                return;
            }

            this.$membershipModal.removeClass('is-visible');
            this.$membershipModal.attr('aria-hidden', 'true');
            $('body').removeClass('sffc-modal-open');
            this.state.membershipModalOpen = false;
        },

        // Update load more button
        updateLoadMoreButton: function(hasMore) {
            const $btn = $('.sffc-load-more-btn');
            if (hasMore) {
                $btn.show();
            } else {
                $btn.hide();
            }
        },
        
        // Handle company click
        handleCompanyClick: function(e) {
            e.preventDefault();
            const $card = $(e.currentTarget);
            const companyId = $card.data('company-id');
            
            this.loadCompanyProfile(companyId);
        },
        
        // Load company profile
        loadCompanyProfile: function(companyId) {
            const $sidebar = $('#companyProfileSidebar');
            const $content = $sidebar.find('.sffc-sidebar-content');
            const $title = $sidebar.find('.sffc-sidebar-title');
            
            // Show sidebar with loading state
            $sidebar.addClass('active');
            $content.html('<div class="sffc-loading-spinner">Loading company profile...</div>');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_company_profile',
                    company_id: companyId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const profile = response.data;
                        $title.text(profile.basic_info.name);
                        $content.html(this.renderCompanyProfile(profile));
                    }
                }.bind(this),
                error: function() {
                    $content.html('<p>Error loading company profile</p>');
                }
            });
        },
        
        // Render company profile
        renderCompanyProfile: function(profile) {
            const html = `
                <div class="sffc-profile-section">
                    <h4>Overview</h4>
                    <p><strong>AUM:</strong> ${profile.basic_info.aum}</p>
                    <p><strong>Founded:</strong> ${profile.basic_info.founded}</p>
                    <p><strong>Headquarters:</strong> ${profile.basic_info.headquarters}</p>
                </div>
                
                <div class="sffc-profile-section">
                    <h4>Current Metrics</h4>
                    <div class="sffc-profile-metrics">
                        <div class="sffc-profile-metric">
                            <span class="value">${profile.metrics.portfolio_count}</span>
                            <span class="label">Portfolio Companies</span>
                        </div>
                        <div class="sffc-profile-metric">
                            <span class="value">${profile.metrics.news_today}</span>
                            <span class="label">News Today</span>
                        </div>
                        <div class="sffc-profile-metric">
                            <span class="value">${profile.metrics.active_deals}</span>
                            <span class="label">Active Deals</span>
                        </div>
                    </div>
                </div>
                
                <div class="sffc-profile-section">
                    <h4>Recent News</h4>
                    <div class="sffc-profile-news">
                        ${profile.recent_news.map(news => `
                            <div class="sffc-news-item">
                                <h5>${news.title}</h5>
                                <p>${news.summary}</p>
                                <span class="time">${news.time_ago}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            return html;
        },
        
        // Close sidebar
        closeSidebar: function() {
            $('#companyProfileSidebar').removeClass('active');
        },
        
        // Handle card action
        handleCardAction: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const action = $btn.data('action');
            const $card = $btn.closest('.sffc-intelligence-card');
            const cardId = $card.data('card-id');
            
            switch(action) {
                case 'details':
                    this.showCardDetails(cardId);
                    break;
                case 'track':
                    this.trackCard(cardId);
                    break;
                default:
                    console.log('Unknown action:', action);
            }
        },

        // Toggle quick interest state
        handleInterestToggle: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.sffc-intelligence-card');
            const cardId = $card.data('card-id');

            if (!cardId) {
                return;
            }

            const isActive = !$card.hasClass('is-interested');
            $card.toggleClass('is-interested', isActive).toggleClass('sffc-card-pulse', isActive);
            $btn.toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');

            this.updateInterestState(cardId, isActive);

            if (isActive) {
                setTimeout(function() {
                    $card.removeClass('sffc-card-pulse');
                }, 400);
            }
        },

        // Handle dismiss action to replace card
        handleCardDismiss: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.sffc-intelligence-card');
            const cardId = $card.data('card-id');
            const cardType = $card.data('card-type') || 'jobs';

            if (!$card.length || !cardId || this.state.isReplacingCard) {
                return;
            }

            this.dismissCard($card, cardId, cardType);
        },
        
        // Show card details
        showCardDetails: function(cardId) {
            // This would open a modal or expand the card
            console.log('Showing details for card:', cardId);
            
            // Get card element
            const $card = $(`.sffc-intelligence-card[data-card-id="${cardId}"]`);
            
            // Toggle expanded state
            $card.toggleClass('expanded');
            
            // If expanding, load additional details
            if ($card.hasClass('expanded')) {
                this.loadCardDetails(cardId, $card);
            }
        },
        
        // Load card details
        loadCardDetails: function(cardId, $card) {
            // This would fetch additional details via AJAX
            console.log('Loading details for card:', cardId);
        },
        
        // Track card/story
        trackCard: function(cardId) {
            const $card = $(`.sffc-intelligence-card[data-card-id="${cardId}"]`);
            const $btn = $card.find('.sffc-action-btn[data-action="track"]');
            
            // Update button state
            $btn.text('Tracking...').prop('disabled', true);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_track_story',
                    card_id: cardId,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('✓ Tracked').addClass('tracked');
                    } else {
                        $btn.text('Track Story').prop('disabled', false);
                    }
                },
                error: function() {
                    $btn.text('Track Story').prop('disabled', false);
                }
            });
        },

        // Remove a card and request a replacement
        dismissCard: function($card, cardId, cardType) {
            this.state.isReplacingCard = true;
            this.updateInterestState(cardId, false);
            $card.addClass('is-dismissing');

            const excludeIds = this.collectCardIds();
            if (!excludeIds.includes(cardId)) {
                excludeIds.push(cardId);
            }

            const filters = this.collectFilters();
            if (!filters.type || filters.type === 'all') {
                filters.type = cardType || 'jobs';
            }

            this.fetchReplacementCard(filters, excludeIds, cardType)
                .done(function(response) {
                    const $grid = $('.sffc-cards-grid');
                    if (response.success && response.data && response.data.html) {
                        const $newCard = $(response.data.html);
                        $newCard.hide();
                        $card.fadeOut(200, function() {
                            $(this).remove();
                            if ($newCard.length) {
                                $grid.append($newCard);
                                $newCard.fadeIn(250);
                                this.applyInterestState($newCard);
                                this.initCharts();
                            }
                        }.bind(this));
                    } else {
                        $card.fadeOut(200, function() {
                            $(this).remove();
                        });
                    }
                }.bind(this))
                .fail(function() {
                    $card.fadeOut(200, function() {
                        $(this).remove();
                    });
                })
                .always(function() {
                    this.state.isReplacingCard = false;
                }.bind(this));
        },

        fetchReplacementCard: function(filters, excludeIds, cardType) {
            return $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_replace_card',
                    filters: filters,
                    exclude_ids: excludeIds,
                    card_type: cardType,
                    nonce: this.config.nonce
                }
            });
        },

        collectCardIds: function() {
            const ids = [];
            $('.sffc-intelligence-card').each(function() {
                const cardId = $(this).data('card-id');
                if (cardId) {
                    ids.push(cardId);
                }
            });
            return ids;
        },

        updateInterestState: function(cardId, isActive) {
            const set = new Set(this.state.interestedCardIds || []);
            if (isActive) {
                set.add(cardId);
            } else {
                set.delete(cardId);
            }
            this.state.interestedCardIds = Array.from(set);
            this.saveInterestState();
        },

        saveInterestState: function() {
            try {
                localStorage.setItem('sffc_interested_cards', JSON.stringify(this.state.interestedCardIds || []));
            } catch (error) {
                console.warn('Unable to persist interest state', error);
            }
        },

        restoreInterestState: function() {
            let stored = [];
            try {
                const raw = localStorage.getItem('sffc_interested_cards');
                if (raw) {
                    stored = JSON.parse(raw);
                }
            } catch (error) {
                stored = [];
            }

            if (!Array.isArray(stored)) {
                stored = [];
            }

            this.state.interestedCardIds = stored;
            this.applyInterestState();
        },

        applyInterestState: function($scope) {
            const ids = new Set(this.state.interestedCardIds || []);
            const $cards = $scope && $scope.length ? $scope : $('.sffc-intelligence-card');

            $cards.each(function() {
                const $card = $(this);
                const cardId = $card.data('card-id');
                const isActive = cardId && ids.has(cardId);
                $card.toggleClass('is-interested', !!isActive);
                const $interestBtn = $card.find('.sffc-card-interest');
                if ($interestBtn.length) {
                    $interestBtn.toggleClass('is-active', !!isActive).attr('aria-pressed', isActive ? 'true' : 'false');
                }
            });
        },
        
        // Initialize charts
        initCharts: function() {
            // Wait for Chart.js to load
            if (typeof Chart === 'undefined') {
                setTimeout(this.initCharts.bind(this), 100);
                return;
            }
            
            // Get all chart data
            if (window.sffcChartData) {
                Object.keys(window.sffcChartData).forEach(function(chartId) {
                    const canvas = document.getElementById(chartId);
                    if (canvas && !this.state.charts[chartId]) {
                        this.createChart(canvas, window.sffcChartData[chartId]);
                    }
                }.bind(this));
            }
        },
        
        // Create chart
        createChart: function(canvas, data) {
            const ctx = canvas.getContext('2d');
            
            const chart = new Chart(ctx, {
                type: data.type || 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        borderColor: '#B08D57',
                        backgroundColor: 'rgba(176, 141, 87, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: '#0F172A',
                            titleColor: '#FFF',
                            bodyColor: '#FFF',
                            padding: 8,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            display: false
                        },
                        y: {
                            display: false
                        }
                    }
                }
            });
            
            this.state.charts[canvas.id] = chart;
        },
        
        // Initialize company profiles
        initCompanyProfiles: function() {
            // Preload top companies
            const topCompanies = ['kkr', 'blackstone', 'apollo', 'carlyle'];
            
            topCompanies.forEach(function(companySlug) {
                // Preload company data in background
                this.preloadCompanyData(companySlug);
            }.bind(this));
        },
        
        // Preload company data
        preloadCompanyData: function(companySlug) {
            // Cache company data for faster loading
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_preload_company',
                    company_slug: companySlug,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    // Store in local cache
                    if (response.success) {
                        this.cacheCompanyData(companySlug, response.data);
                    }
                }.bind(this)
            });
        },
        
        // Cache company data
        cacheCompanyData: function(slug, data) {
            if (typeof(Storage) !== "undefined") {
                const cacheKey = 'sffc_company_' + slug;
                const cacheData = {
                    data: data,
                    timestamp: Date.now()
                };
                localStorage.setItem(cacheKey, JSON.stringify(cacheData));
            }
        },
        
        // Get cached company data
        getCachedCompanyData: function(slug) {
            if (typeof(Storage) !== "undefined") {
                const cacheKey = 'sffc_company_' + slug;
                const cached = localStorage.getItem(cacheKey);
                
                if (cached) {
                    const cacheData = JSON.parse(cached);
                    const age = Date.now() - cacheData.timestamp;
                    
                    // Cache valid for 1 hour
                    if (age < 3600000) {
                        return cacheData.data;
                    }
                }
            }
            return null;
        },
        
        // Start auto refresh
        startAutoRefresh: function() {
            if (this.config.refreshInterval > 0) {
                this.state.autoRefresh = setInterval(function() {
                    this.refreshNewContent();
                }.bind(this), this.config.refreshInterval);
            }
        },
        
        // Refresh new content
        refreshNewContent: function() {
            // Only refresh if not currently loading
            if (!this.state.isLoading) {
                $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sffc_check_new_content',
                        last_check: Date.now(),
                        nonce: this.config.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.has_new) {
                            this.showNewContentNotification(response.data.count);
                        }
                    }.bind(this)
                });
            }
        },
        
        // Show new content notification
        showNewContentNotification: function(count) {
            const notification = $('<div class="sffc-new-content-notification">')
                .text(`${count} new updates available`)
                .append('<button class="sffc-refresh-btn">Refresh</button>');
            
            $('body').append(notification);
            
            notification.fadeIn(300);
            
            // Handle refresh click
            notification.find('.sffc-refresh-btn').on('click', function() {
                location.reload();
            });
            
            // Auto hide after 10 seconds
            setTimeout(function() {
                notification.fadeOut(300, function() {
                    notification.remove();
                });
            }, 10000);
        },
        
        // Stop auto refresh
        stopAutoRefresh: function() {
            if (this.state.autoRefresh) {
                clearInterval(this.state.autoRefresh);
                this.state.autoRefresh = null;
            }
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        PEIntelligence.init();
    });
    
    // Clean up on page unload
    $(window).on('beforeunload', function() {
        PEIntelligence.stopAutoRefresh();
    });
    
})(jQuery);
