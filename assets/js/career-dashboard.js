/**
 * Career Intelligence Dashboard JavaScript
 *
 * Handles chart rendering, data fetching, and UI interactions.
 */

(function($) {
    'use strict';

    // Dashboard configuration
    const Dashboard = {
        charts: {},
        data: {},
        currentRange: '6m',
        currentSeries: 'locations',
        isLoading: false,

        /**
         * Initialize the dashboard
         */
        init: function() {
            if (!$('.sffc-career-dashboard').length) {
                return;
            }

            this.bindEvents();
            this.loadInitialData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Refresh button
            $('#sffc-refresh-dashboard').on('click', () => this.refreshDashboard());

            // Settings button
            $('#sffc-dashboard-settings').on('click', () => this.openSettings());

            // Chart toggles
            $('.sffc-toggle-btn').on('click', (e) => {
                const series = $(e.currentTarget).data('series');
                this.switchSeries(series);
            });

            // Date range buttons
            $('.sffc-range-btn').on('click', (e) => {
                const range = $(e.currentTarget).data('range');
                this.switchRange(range);
            });

            // Market filter
            $('#sffc-market-filter').on('change', (e) => {
                this.loadMarketIntel($(e.target).val());
            });

            // News/Deals feed filter buttons (All, News, Deals)
            $(document).on('click', '.sffc-inst-controls .sffc-pill[data-feed]', (e) => {
                const $btn = $(e.currentTarget);
                const feed = $btn.data('feed');

                // Update active state
                $btn.siblings('.sffc-pill').removeClass('active');
                $btn.addClass('active');

                // Load filtered content
                this.loadMarketIntel(feed);
            });

            // Location comparison
            $('#sffc-location-1, #sffc-location-2').on('change', () => {
                this.updateSalaryComparison();
            });

            // Notification toggles
            $('.sffc-toggle-option input').on('change', (e) => {
                this.savePreference(e.target.name, e.target.checked);
            });

            // Edit profile button
            $('#sffc-edit-profile').on('click', () => this.openProfileEditor());

            // Export chart buttons
            $('#sffc-export-trends').on('click', () => this.exportTrendsChart());
            $('#sffc-export-skills').on('click', () => this.exportSkillsChart());

            // Membership events
            this.bindMembershipEvents();

            // Load More Jobs button
            $('#sffc-load-more-jobs').on('click', () => this.loadMoreJobs());
        },

        /**
         * Bind membership-related events
         */
        bindMembershipEvents: function() {
            // Membership badge click - open modal
            $('#sffc-membership-badge').on('click', () => this.openMembershipModal());

            // Membership badge keyboard support
            $('#sffc-membership-badge').on('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.openMembershipModal();
                }
            });

            // Upgrade button click
            $('#sffc-upgrade-trigger').on('click', () => this.openMembershipModal());

            // Close membership modal
            $('#sffc-close-membership-modal').on('click', () => this.closeMembershipModal());
            $('#sffc-membership-modal').on('click', (e) => {
                if ($(e.target).is('#sffc-membership-modal')) {
                    this.closeMembershipModal();
                }
            });

            // Close upgrade prompt
            $('#sffc-close-upgrade-prompt, #sffc-prompt-dismiss').on('click', () => this.closeUpgradePrompt());
            $('#sffc-upgrade-prompt').on('click', (e) => {
                if ($(e.target).is('#sffc-upgrade-prompt')) {
                    this.closeUpgradePrompt();
                }
            });

            // Escape key to close modals
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    if ($('#sffc-membership-modal').is(':visible')) {
                        this.closeMembershipModal();
                    }
                    if ($('#sffc-upgrade-prompt').is(':visible')) {
                        this.closeUpgradePrompt();
                    }
                }
            });
        },

        /**
         * Open membership modal
         */
        openMembershipModal: function() {
            const $modal = $('#sffc-membership-modal');
            $modal.css('display', 'flex').hide().fadeIn(200);
            $('body').addClass('sffc-modal-open');

            // Load fresh membership data
            this.loadMembershipData();
        },

        /**
         * Close membership modal
         */
        closeMembershipModal: function() {
            $('#sffc-membership-modal').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('sffc-modal-open');
        },

        /**
         * Load membership data for modal
         */
        loadMembershipData: function() {
            this.ajaxRequest('sffc_get_membership_info').then((response) => {
                if (response.success) {
                    this.renderMembershipUsage(response.data.usage);
                }
            });
        },

        /**
         * Render membership usage stats in modal
         */
        renderMembershipUsage: function(usage) {
            if (!usage) return;

            const $container = $('#sffc-usage-items');
            $container.empty();

            const usageTypes = [
                { key: 'job_matches', label: 'Job Matches' },
                { key: 'saved_articles', label: 'Saved Articles' },
                { key: 'profile_views', label: 'Profile Views' },
            ];

            usageTypes.forEach(type => {
                const data = usage[type.key] || { current: 0, limit: 0, percentage: 0, unlimited: false };
                const displayLimit = data.unlimited ? '∞' : data.limit;
                const barWidth = data.unlimited ? 0 : data.percentage;

                $container.append(`
                    <div class="sffc-usage-item">
                        <span class="sffc-usage-label">${type.label}</span>
                        <div class="sffc-usage-progress">
                            <div class="sffc-usage-progress-bar" style="width: ${barWidth}%"></div>
                        </div>
                        <span class="sffc-usage-count">${data.current}/${displayLimit}</span>
                    </div>
                `);
            });
        },

        /**
         * Show upgrade prompt for a feature
         */
        showUpgradePrompt: function(feature, currentValue, upgradeValue, upgradeUrl) {
            const $prompt = $('#sffc-upgrade-prompt');
            const titles = {
                'trends_range': 'Unlock Full Trend History',
                'skills_limit': 'Unlock Full Skills Analysis',
                'news_limit': 'Unlock Unlimited News',
                'salary_locations': 'Unlock More Salary Locations',
                'export_enabled': 'Unlock Export Feature',
                'ai_insights': 'Unlock AI-Powered Insights',
            };

            $('#sffc-prompt-title').text(titles[feature] || 'Unlock This Feature');
            $('#sffc-prompt-current-value').text(currentValue);
            $('#sffc-prompt-upgrade-value').text(upgradeValue);
            $('#sffc-prompt-upgrade-btn').attr('href', upgradeUrl || '/membership/');

            $prompt.css('display', 'flex').hide().fadeIn(200);
            $('body').addClass('sffc-modal-open');
        },

        /**
         * Close upgrade prompt
         */
        closeUpgradePrompt: function() {
            $('#sffc-upgrade-prompt').fadeOut(200, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('sffc-modal-open');
        },

        /**
         * Check feature access and show prompt if needed
         */
        checkFeatureAccess: function(feature, value, callback) {
            this.ajaxRequest('sffc_check_feature_access', { feature, value }).then((response) => {
                if (response.success) {
                    if (response.data.has_access) {
                        if (callback) callback();
                    } else {
                        const prompt = response.data.upgrade_prompt;
                        if (prompt) {
                            this.showUpgradePrompt(
                                feature,
                                prompt.current_value,
                                prompt.upgrade_value,
                                prompt.upgrade_url
                            );
                        }
                    }
                }
            });
        },

        /**
         * Load initial dashboard data
         */
        loadInitialData: function() {
            this.showLoading();

            // Load all data in parallel
            Promise.all([
                this.loadStats(),
                this.loadTrends(),
                this.loadSkillsAnalysis(),
                this.loadMarketIntel('all'),
                this.loadSalaryData()
            ]).then(() => {
                this.hideLoading();
            }).catch((error) => {
                console.error('Dashboard load error:', error);
                this.hideLoading();
                this.showError('Failed to load dashboard data');
            });
        },

        /**
         * Load dashboard stats
         */
        loadStats: function() {
            return this.ajaxRequest('sffc_dashboard_get_stats').then((response) => {
                if (response.success) {
                    this.data.stats = response.data;
                    this.renderStatCards(response.data);
                }
            });
        },

        /**
         * Load trends data
         */
        loadTrends: function() {
            return this.ajaxRequest('sffc_dashboard_get_trends', {
                range: this.currentRange,
                series: this.currentSeries
            }).then((response) => {
                if (response.success) {
                    this.data.trends = response.data;
                    this.renderTrendsChart(response.data);
                }
            });
        },

        /**
         * Load skills analysis
         */
        loadSkillsAnalysis: function() {
            return this.ajaxRequest('sffc_dashboard_get_skills').then((response) => {
                if (response.success) {
                    this.data.skills = response.data;
                    this.renderSkillsSummary(response.data.summary);
                    this.renderSkillsChart(response.data);
                    this.renderSkillsRadar(response.data.radar_data);
                    this.renderSkillsGaps(response.data.gaps);
                    this.renderUpskillRecommendations(response.data.recommendations);
                }
            });
        },

        /**
         * Load market intelligence
         */
        loadMarketIntel: function(filter) {
            return this.ajaxRequest('sffc_dashboard_get_market_intel', { filter }).then((response) => {
                if (response.success) {
                    this.data.market = response.data;
                    this.renderTrendingTopics(response.data.trending_topics);
                    this.renderMarketFeed(response.data);
                    this.renderMarketSignals(response.data.signals);
                    this.renderSavedArticles(response.data.saved_articles);
                }
            });
        },

        /**
         * Load salary data
         */
        loadSalaryData: function() {
            const location1 = $('#sffc-location-1').val() || 'london';
            const location2 = $('#sffc-location-2').val() || 'new-york';

            return this.ajaxRequest('sffc_dashboard_get_salary_data', {
                location1,
                location2
            }).then((response) => {
                if (response.success) {
                    this.data.salary = response.data;
                    this.renderSalarySection(response.data);
                }
            });
        },

        /**
         * Render stat cards with mini charts
         */
        renderStatCards: function(stats) {
            // Update Overview Metrics - use actual values, default to 0 if not available
            $('[data-value="total-applications"]').text(stats.total_applications || 0);
            $('[data-value="high-matches"]').text(stats.high_matches || 0);
            $('[data-value="networking-intros"]').text(stats.networking_intros || 0);
            $('[data-value="recruiter-intros"]').text(stats.recruiter_intros || 0);

            // Render the new analytics charts
            this.renderIndustryDonutChart(stats.industry_data);
            this.renderSeniorityChart(stats.seniority_data);
            this.renderLocationBarChart(stats.location_data);
        },

        /**
         * Render Industry Donut Chart
         */
        renderIndustryDonutChart: function(data) {
            const canvas = document.getElementById('sffc-industry-donut-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Default data if none provided
            const industryData = data || {
                labels: ['Asset Management', 'Private Equity', 'Investment Banking', 'Private Credit', 'Other'],
                values: [30, 28, 24, 12, 6]
            };

            // Destroy existing chart
            if (this.charts['industry-donut']) {
                this.charts['industry-donut'].destroy();
            }

            const colors = ['#1e3a5f', '#2563eb', '#0891b2', '#60a5fa', '#94a3b8'];

            this.charts['industry-donut'] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: industryData.labels,
                    datasets: [{
                        data: industryData.values,
                        backgroundColor: colors,
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 6,
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + '%';
                                }
                            }
                        }
                    }
                }
            });

            // Render legend
            this.renderIndustryLegend(industryData.labels, industryData.values, colors);
        },

        /**
         * Render Industry Legend
         */
        renderIndustryLegend: function(labels, values, colors) {
            const $legend = $('#sffc-industry-legend');
            $legend.empty();

            labels.forEach((label, i) => {
                $legend.append(`
                    <div class="sffc-legend-item">
                        <span class="sffc-legend-dot" style="background: ${colors[i]}"></span>
                        <span class="sffc-legend-label">${label}</span>
                        <span class="sffc-legend-value">${values[i]}%</span>
                    </div>
                `);
            });
        },

        /**
         * Render Seniority Bar Chart
         */
        renderSeniorityChart: function(data) {
            const canvas = document.getElementById('sffc-seniority-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Default data if none provided
            const seniorityData = data || {
                labels: ['Associate', 'VP', 'Director', 'MD/Partner', 'C-Level'],
                values: [12, 28, 35, 18, 7]
            };

            // Destroy existing chart
            if (this.charts['seniority']) {
                this.charts['seniority'].destroy();
            }

            this.charts['seniority'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: seniorityData.labels,
                    datasets: [{
                        data: seniorityData.values,
                        backgroundColor: '#2563eb',
                        borderRadius: 4,
                        barThickness: 24
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: '#e2e8f0',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: { size: 10 }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#0f172a',
                                font: { size: 11, weight: '500' }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render Location Bar Chart
         */
        renderLocationBarChart: function(data) {
            const canvas = document.getElementById('sffc-location-bar-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Try to get data from: 1) passed data, 2) data attribute on canvas, 3) default
            let locationData = data;

            if (!locationData || !locationData.labels || locationData.labels.length === 0) {
                // Try to read from data-locations attribute
                const dataAttr = canvas.getAttribute('data-locations');
                if (dataAttr) {
                    try {
                        const parsedData = JSON.parse(dataAttr);
                        if (Array.isArray(parsedData) && parsedData.length > 0) {
                            // Data format from PHP is array of {country_code, count, share, ...}
                            locationData = {
                                labels: parsedData.map(function(item) { return item.country_code; }),
                                values: parsedData.map(function(item) { return item.count; })
                            };
                        }
                    } catch (e) {
                        console.warn('Failed to parse location data attribute:', e);
                    }
                }
            }

            // Final fallback to default
            if (!locationData || !locationData.labels || locationData.labels.length === 0) {
                locationData = {
                    labels: ['GB', 'US', 'AE', 'IT', 'EG'],
                    values: [0, 0, 0, 0, 0]
                };
            }

            // Destroy existing chart
            if (this.charts['location-bar']) {
                this.charts['location-bar'].destroy();
            }

            this.charts['location-bar'] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: locationData.labels,
                    datasets: [{
                        data: locationData.values,
                        backgroundColor: ['#1e3a5f', '#2563eb', '#0891b2', '#60a5fa', '#94a3b8'],
                        borderRadius: 4
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
                            backgroundColor: '#0f172a',
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11 },
                            padding: 10,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#0f172a',
                                font: { size: 11, weight: '500' }
                            }
                        },
                        y: {
                            grid: {
                                color: '#e2e8f0',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render mini donut chart
         */
        renderMiniDonut: function(canvasId, value, color) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Destroy existing chart
            if (this.charts[canvasId]) {
                this.charts[canvasId].destroy();
            }

            this.charts[canvasId] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [value || 0, 100 - (value || 0)],
                        backgroundColor: [color, '#e2e8f0'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '75%',
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    animation: {
                        animateRotate: true,
                        duration: 800
                    }
                }
            });
        },

        /**
         * Update trend indicator
         */
        updateTrendIndicator: function(type, trend) {
            const $indicator = $(`[data-trend="${type}"]`);

            if (!trend) return;

            $indicator.removeClass('sffc-trend-up sffc-trend-down sffc-trend-neutral');

            if (trend.direction === 'up') {
                $indicator.addClass('sffc-trend-up');
                $indicator.find('svg').html('<path d="M7 14l5-5 5 5H7z"/>');
                $indicator.find('span').text(`+${Math.abs(trend.change)}%`);
            } else if (trend.direction === 'down') {
                $indicator.addClass('sffc-trend-down');
                $indicator.find('svg').html('<path d="M7 10l5 5 5-5H7z"/>');
                $indicator.find('span').text(`-${Math.abs(trend.change)}%`);
            } else {
                $indicator.addClass('sffc-trend-neutral');
                $indicator.find('svg').html('<path d="M19 13H5v-2h14v2z"/>');
                $indicator.find('span').text('Stable');
            }
        },

        /**
         * Render main trends chart
         */
        renderTrendsChart: function(data) {
            const canvas = document.getElementById('sffc-trends-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Destroy existing chart
            if (this.charts.trends) {
                this.charts.trends.destroy();
            }

            // Hide loading
            $('.sffc-trends-section .sffc-chart-loading').addClass('hidden');

            this.charts.trends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: data.datasets || []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12,
                                    family: "'Inter', sans-serif"
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleFont: {
                                size: 13,
                                family: "'Inter', sans-serif"
                            },
                            bodyFont: {
                                size: 12,
                                family: "'Inter', sans-serif"
                            },
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 12,
                                    family: "'Inter', sans-serif"
                                },
                                color: '#64748b'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0'
                            },
                            ticks: {
                                font: {
                                    size: 12,
                                    family: "'Inter', sans-serif"
                                },
                                color: '#64748b'
                            }
                        }
                    }
                }
            });

            // Update AI insight
            if (data.insight) {
                $('#sffc-ai-insight').text(data.insight);
            }
        },

        /**
         * Render skills chart
         */
        renderSkillsChart: function(data) {
            const canvas = document.getElementById('sffc-skills-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            // Destroy existing chart
            if (this.charts.skills) {
                this.charts.skills.destroy();
            }

            // Hide loading
            $('.sffc-skills-section .sffc-chart-loading').addClass('hidden');

            const skills = data.skills || [];
            const labels = skills.map(s => s.name);
            const values = skills.map(s => s.demand);
            const colors = skills.map(s => {
                if (s.demand >= 70) return '#059669';
                if (s.demand >= 40) return '#d97706';
                return '#dc2626';
            });

            this.charts.skills = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Demand Score',
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `Demand: ${context.raw}/100`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            max: 100,
                            grid: {
                                color: '#e2e8f0'
                            },
                            ticks: {
                                font: { size: 11 },
                                color: '#64748b'
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 12 },
                                color: '#1e293b'
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render skills summary stats
         */
        renderSkillsSummary: function(summary) {
            if (!summary) return;

            $('[data-summary="total"]').text(summary.total_skills || 0);
            $('[data-summary="high-demand"]').text(summary.high_demand_skills || 0);
            $('[data-summary="trending"]').text(summary.trending_skills || 0);
            $('[data-summary="salary-premium"]').text(summary.estimated_salary_premium || '0%');
        },

        /**
         * Render skills radar chart
         */
        renderSkillsRadar: function(radarData) {
            const canvas = document.getElementById('sffc-skills-radar');
            if (!canvas || !radarData) return;

            const ctx = canvas.getContext('2d');

            // Destroy existing chart
            if (this.charts.skillsRadar) {
                this.charts.skillsRadar.destroy();
            }

            // Hide loading
            $('.sffc-skills-radar-section .sffc-chart-loading').addClass('hidden');

            this.charts.skillsRadar = new Chart(ctx, {
                type: 'radar',
                data: radarData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20,
                                font: { size: 10 },
                                color: '#64748b',
                                backdropColor: 'transparent'
                            },
                            grid: {
                                color: '#e2e8f0'
                            },
                            angleLines: {
                                color: '#e2e8f0'
                            },
                            pointLabels: {
                                font: { size: 11 },
                                color: '#1e293b'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 11 }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            padding: 12,
                            cornerRadius: 8
                        }
                    }
                }
            });
        },

        /**
         * Render skills gaps
         */
        renderSkillsGaps: function(gaps) {
            const $list = $('#sffc-skills-gap-list');
            $list.empty();

            if (!gaps || gaps.length === 0) {
                $list.html('<p class="sffc-no-data">No significant skill gaps identified. Great job!</p>');
                return;
            }

            gaps.slice(0, 6).forEach(gap => {
                const trendIcon = gap.trend === 'up' ?
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="sffc-trend-icon-up"><path d="M7 14l5-5 5 5H7z"/></svg>' :
                    gap.trend === 'down' ?
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" class="sffc-trend-icon-down"><path d="M7 10l5 5 5-5H7z"/></svg>' :
                    '';

                const importanceClass = gap.importance === 'High' ? 'importance-high' :
                    gap.importance === 'Medium' ? 'importance-medium' : 'importance-low';

                $list.append(`
                    <div class="sffc-gap-item ${importanceClass}">
                        <div class="sffc-gap-info">
                            <span class="sffc-gap-skill">${this.escapeHtml(gap.skill)}</span>
                            <span class="sffc-gap-category">${this.escapeHtml(gap.category)}</span>
                        </div>
                        <div class="sffc-gap-meta">
                            <span class="sffc-gap-demand">${gap.demand}% demand ${trendIcon}</span>
                            <span class="sffc-gap-salary">+${gap.salary_impact}% salary</span>
                        </div>
                        <span class="sffc-gap-importance">${this.escapeHtml(gap.importance)}</span>
                    </div>
                `);
            });
        },

        /**
         * Render upskill recommendations
         */
        renderUpskillRecommendations: function(recommendations) {
            const $list = $('#sffc-upskill-list');
            $list.empty();

            if (!recommendations || recommendations.length === 0) {
                $list.html('<p class="sffc-no-data">No recommendations at this time</p>');
                return;
            }

            recommendations.forEach(rec => {
                const importanceClass = rec.importance === 'High' ? 'importance-high' :
                    rec.importance === 'Medium' ? 'importance-medium' : '';

                const coursesHtml = rec.courses && rec.courses.length > 0 ?
                    rec.courses.map(c => `<span class="sffc-course-tag">${this.escapeHtml(c.name || c)}</span>`).join('') : '';

                $list.append(`
                    <div class="sffc-recommendation-item ${importanceClass}">
                        <div class="sffc-recommendation-header">
                            <span class="sffc-recommendation-skill">${this.escapeHtml(rec.skill)}</span>
                            <span class="sffc-recommendation-importance">${this.escapeHtml(rec.importance)}</span>
                        </div>
                        <p class="sffc-recommendation-desc">${this.escapeHtml(rec.description || '')}</p>
                        <div class="sffc-recommendation-meta">
                            <span class="sffc-recommendation-salary">${this.escapeHtml(rec.salary_uplift || '')}</span>
                            <span class="sffc-recommendation-time">${this.escapeHtml(rec.time_estimate || '')}</span>
                        </div>
                        ${coursesHtml ? `<div class="sffc-recommendation-courses">${coursesHtml}</div>` : ''}
                    </div>
                `);
            });
        },

        /**
         * Render trending topics
         */
        renderTrendingTopics: function(topics) {
            const $container = $('#sffc-trending-topics');
            if (!$container.length) return;

            $container.empty();

            if (!topics || topics.length === 0) {
                $container.html('<span class="sffc-no-data">No trending topics</span>');
                return;
            }

            topics.slice(0, 8).forEach(topic => {
                const trendClass = topic.trend === 'up' ? 'trend-up' : topic.trend === 'down' ? 'trend-down' : 'trend-neutral';
                $container.append(`
                    <button class="sffc-topic-tag ${trendClass}" data-topic="${this.escapeHtml(topic.name)}">
                        ${this.escapeHtml(topic.name)}
                        <span class="sffc-topic-count">${topic.count || ''}</span>
                    </button>
                `);
            });

            // Bind click events for filtering
            $container.find('.sffc-topic-tag').on('click', (e) => {
                const topic = $(e.currentTarget).data('topic');
                this.filterByTopic(topic);
            });
        },

        /**
         * Filter market feed by topic
         */
        filterByTopic: function(topic) {
            // Toggle active state
            const $tag = $(`.sffc-topic-tag[data-topic="${topic}"]`);
            const wasActive = $tag.hasClass('active');

            $('.sffc-topic-tag').removeClass('active');

            if (!wasActive) {
                $tag.addClass('active');
                // Filter displayed items
                $('.sffc-feed-item').each(function() {
                    const title = $(this).find('.sffc-feed-title').text().toLowerCase();
                    const content = $(this).find('.sffc-feed-excerpt').text().toLowerCase();
                    const matches = title.includes(topic.toLowerCase()) || content.includes(topic.toLowerCase());
                    $(this).toggle(matches);
                });
            } else {
                // Show all items
                $('.sffc-feed-item').show();
            }
        },

        /**
         * Render market feed
         */
        renderMarketFeed: function(data) {
            const $feed = $('#sffc-market-feed');
            $feed.empty();

            const items = [...(data.news || []), ...(data.deals || [])];

            if (items.length === 0) {
                $feed.html('<p class="sffc-no-data">No market updates available</p>');
                return;
            }

            // Sort by relevance if available
            items.sort((a, b) => (b.relevance || 0) - (a.relevance || 0));

            items.slice(0, 12).forEach(item => {
                const isNews = item.type === 'news';
                const iconClass = isNews ? 'type-news' : 'type-deal';
                const icon = isNews ?
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 002-2V4a2 2 0 00-2-2H8a2 2 0 00-2 2v16a2 2 0 01-2 2zm0 0a2 2 0 01-2-2v-9c0-1.1.9-2 2-2h2"/></svg>' :
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>';

                // Sentiment indicator
                const sentimentClass = item.sentiment === 'positive' ? 'sentiment-positive' :
                    item.sentiment === 'negative' ? 'sentiment-negative' : 'sentiment-neutral';
                const sentimentIcon = item.sentiment === 'positive' ?
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5H7z"/></svg>' :
                    item.sentiment === 'negative' ?
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg>' :
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="5" y="11" width="14" height="2"/></svg>';

                // Relevance badge
                const relevanceBadge = item.relevance >= 80 ?
                    '<span class="sffc-relevance-badge high">Highly Relevant</span>' :
                    item.relevance >= 60 ?
                    '<span class="sffc-relevance-badge medium">Relevant</span>' : '';

                // Career impact
                const impactHtml = item.career_impact ?
                    `<p class="sffc-feed-impact"><strong>Career Impact:</strong> ${this.escapeHtml(item.career_impact)}</p>` : '';

                // Excerpt
                const excerptHtml = item.excerpt ?
                    `<p class="sffc-feed-excerpt">${this.escapeHtml(item.excerpt)}</p>` : '';

                // Deal-specific info
                let dealInfoHtml = '';
                if (!isNews && item.deal_size) {
                    dealInfoHtml = `<span class="sffc-deal-size">${this.escapeHtml(item.deal_size)}</span>`;
                }
                if (!isNews && item.deal_type) {
                    dealInfoHtml += `<span class="sffc-deal-type">${this.escapeHtml(item.deal_type)}</span>`;
                }

                $feed.append(`
                    <div class="sffc-feed-item" data-id="${item.id || ''}">
                        <div class="sffc-feed-header">
                            <div class="sffc-feed-icon ${iconClass}">${icon}</div>
                            <div class="sffc-feed-meta-top">
                                <span class="sffc-feed-source">${this.escapeHtml(item.source || '')}</span>
                                <span class="sffc-feed-date">${this.escapeHtml(item.date || '')}</span>
                                ${relevanceBadge}
                            </div>
                        </div>
                        <div class="sffc-feed-content">
                            <h4 class="sffc-feed-title">${this.escapeHtml(item.title)}</h4>
                            ${excerptHtml}
                            ${impactHtml}
                            <div class="sffc-feed-footer">
                                <div class="sffc-feed-indicators">
                                    <span class="sffc-sentiment ${sentimentClass}" title="Market Sentiment">
                                        ${sentimentIcon}
                                        <span>${this.escapeHtml(item.sentiment || 'neutral')}</span>
                                    </span>
                                    ${dealInfoHtml}
                                </div>
                                <div class="sffc-feed-actions">
                                    <button class="sffc-save-article-btn" data-id="${item.id || ''}" data-type="${item.type}" title="Save article">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
                                        </svg>
                                    </button>
                                    ${item.url ? `<a href="${this.escapeHtml(item.url)}" target="_blank" class="sffc-read-more-btn" title="Read more">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/>
                                            <polyline points="15 3 21 3 21 9"/>
                                            <line x1="10" y1="14" x2="21" y2="3"/>
                                        </svg>
                                    </a>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            });

            // Bind save article events
            this.bindSaveArticleEvents();
        },

        /**
         * Bind save article button events
         */
        bindSaveArticleEvents: function() {
            const self = this;

            $('.sffc-save-article-btn').off('click').on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const articleId = $btn.data('id');
                const articleType = $btn.data('type');

                if (!articleId) return;

                // Toggle saved state
                const isSaved = $btn.hasClass('saved');

                self.ajaxRequest('sffc_dashboard_save_article', {
                    article_id: articleId,
                    article_type: articleType,
                    action_type: isSaved ? 'unsave' : 'save'
                }).then((response) => {
                    if (response.success) {
                        $btn.toggleClass('saved');
                        if (!isSaved) {
                            self.showSuccessToast('Article saved');
                            // Refresh saved articles list
                            self.loadMarketIntel($('#sffc-market-filter').val() || 'all');
                        } else {
                            self.showSuccessToast('Article removed from saved');
                        }
                    }
                }).catch(() => {
                    self.showError('Failed to save article');
                });
            });
        },

        /**
         * Render market signals
         */
        renderMarketSignals: function(signals) {
            const $list = $('#sffc-signals-list');
            $list.empty();

            if (!signals || signals.length === 0) {
                $list.html('<p class="sffc-no-data">No market signals detected</p>');
                return;
            }

            signals.forEach(signal => {
                const signalClass = signal.type === 'positive' ? 'signal-positive' :
                    signal.type === 'negative' ? 'signal-negative' : 'signal-neutral';

                const icon = signal.type === 'positive' ?
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5H7z"/></svg>' :
                    signal.type === 'negative' ?
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg>' :
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="4"/></svg>';

                const strengthHtml = signal.strength ?
                    `<span class="sffc-signal-strength strength-${signal.strength}">${this.escapeHtml(signal.strength)}</span>` : '';

                const categoryHtml = signal.category ?
                    `<span class="sffc-signal-category">${this.escapeHtml(signal.category)}</span>` : '';

                $list.append(`
                    <div class="sffc-signal-item ${signalClass}">
                        <div class="sffc-signal-icon">${icon}</div>
                        <div class="sffc-signal-content">
                            <span class="sffc-signal-text">${this.escapeHtml(signal.text)}</span>
                            <div class="sffc-signal-meta">
                                ${categoryHtml}
                                ${strengthHtml}
                            </div>
                        </div>
                    </div>
                `);
            });
        },

        /**
         * Render saved articles
         */
        renderSavedArticles: function(articles) {
            const $list = $('#sffc-saved-articles-list');
            if (!$list.length) return;

            $list.empty();

            if (!articles || articles.length === 0) {
                $list.html('<p class="sffc-no-data">No saved articles yet</p>');
                return;
            }

            articles.slice(0, 5).forEach(article => {
                const typeIcon = article.type === 'news' ?
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22h16a2 2 0 002-2V4a2 2 0 00-2-2H8a2 2 0 00-2 2v16a2 2 0 01-2 2zm0 0a2 2 0 01-2-2v-9c0-1.1.9-2 2-2h2"/></svg>' :
                    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>';

                $list.append(`
                    <div class="sffc-saved-article" data-id="${article.id || ''}">
                        <span class="sffc-saved-type">${typeIcon}</span>
                        <div class="sffc-saved-content">
                            <a href="${this.escapeHtml(article.url || '#')}" target="_blank" class="sffc-saved-title">${this.escapeHtml(article.title)}</a>
                            <span class="sffc-saved-date">${this.escapeHtml(article.saved_date || '')}</span>
                        </div>
                        <button class="sffc-unsave-btn" data-id="${article.id || ''}" data-type="${article.type || 'news'}" title="Remove">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                `);
            });

            // Bind unsave events
            const self = this;
            $list.find('.sffc-unsave-btn').on('click', function(e) {
                e.preventDefault();
                const articleId = $(this).data('id');
                const articleType = $(this).data('type');

                self.ajaxRequest('sffc_dashboard_save_article', {
                    article_id: articleId,
                    article_type: articleType,
                    action_type: 'unsave'
                }).then((response) => {
                    if (response.success) {
                        $(this).closest('.sffc-saved-article').fadeOut(200, function() {
                            $(this).remove();
                            // Check if list is empty
                            if ($list.find('.sffc-saved-article').length === 0) {
                                $list.html('<p class="sffc-no-data">No saved articles yet</p>');
                            }
                        });
                        // Also update main feed button state
                        $(`.sffc-save-article-btn[data-id="${articleId}"]`).removeClass('saved');
                        self.showSuccessToast('Article removed');
                    }
                });
            });
        },

        /**
         * Render salary section
         */
        renderSalarySection: function(data) {
            const estimate = data.estimate || {};
            const currency = estimate.currency || 'GBP';
            const symbol = this.getCurrencySymbol(currency);

            // Update estimate range
            $('[data-value="salary-min"]').text(`${symbol}${this.formatNumber(estimate.min)}`);
            $('[data-value="salary-max"]').text(`${symbol}${this.formatNumber(estimate.max)}`);

            // Update percentile marker
            const percentile = data.percentile || 50;
            $('.sffc-percentile-marker').css('left', `${percentile}%`);

            // Render total compensation
            this.renderTotalCompensation(data.total_compensation, data.bonus);

            // Render salary factors
            this.renderSalaryFactors(data.factors);

            // Render location comparison
            this.renderLocationComparison(data.location_comparison);

            // Render industry chart
            this.renderIndustrySalaryChart(data.industry_data);

            // Render salary trends
            this.renderSalaryTrends(data.trends);

            // Render top quartile tips
            this.renderTopQuartileTips(data.top_quartile_tips);
        },

        /**
         * Render total compensation breakdown
         */
        renderTotalCompensation: function(totalComp, bonus) {
            if (!totalComp) return;

            const symbol = this.getCurrencySymbol(totalComp.currency);

            // Base salary range
            $('#sffc-base-salary-range').text(totalComp.formatted?.base_range || '--');

            // Bonus typical
            $('#sffc-bonus-typical').text(`${symbol}${this.formatNumber(totalComp.bonus?.typical)}`);

            // Total typical
            $('#sffc-total-typical').text(totalComp.formatted?.total_typical || '--');

            // Bonus range
            if (bonus) {
                $('#sffc-bonus-range').text(`${bonus.percentage.min}% - ${bonus.percentage.max}% of base`);
                $('#sffc-bonus-note').text(bonus.notes || '');
            }
        },

        /**
         * Render salary factors
         */
        renderSalaryFactors: function(factors) {
            const $list = $('#sffc-salary-factors');
            $list.empty();

            if (!factors || factors.length === 0) {
                $list.html('<p class="sffc-no-data">Complete your profile for personalized factors</p>');
                return;
            }

            factors.forEach(factor => {
                const impactClass = factor.impact === 'positive' ? 'factor-positive' :
                    factor.impact === 'negative' ? 'factor-negative' : 'factor-neutral';

                const icon = factor.impact === 'positive' ?
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 14l5-5 5 5H7z"/></svg>' :
                    factor.impact === 'negative' ?
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M7 10l5 5 5-5H7z"/></svg>' :
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><rect x="5" y="11" width="14" height="2"/></svg>';

                $list.append(`
                    <div class="sffc-factor-item ${impactClass}">
                        <span class="sffc-factor-icon">${icon}</span>
                        <div class="sffc-factor-content">
                            <span class="sffc-factor-label">${this.escapeHtml(factor.label)}</span>
                            <span class="sffc-factor-value">${this.escapeHtml(factor.value)}</span>
                        </div>
                    </div>
                `);
            });
        },

        /**
         * Render enhanced location comparison
         */
        renderLocationComparison: function(data) {
            if (!data) return;

            const loc1 = $('#sffc-location-1').val();
            const loc2 = $('#sffc-location-2').val();

            // Get location data
            const loc1Data = data[loc1];
            const loc2Data = data[loc2];
            const comparison = data.comparison;

            if (!loc1Data || !loc2Data) return;

            // Update location 1
            $('#sffc-loc1-name').text(this.formatLocationLabel(loc1));
            $('#sffc-loc1-gross').text(loc1Data.formatted?.gross_range || '--');
            $('#sffc-loc1-net').text(loc1Data.formatted?.net_range || '--');
            $('#sffc-loc1-tax').text(`${loc1Data.tax_rate}%`);
            $('#sffc-loc1-col').text(`${loc1Data.cost_of_living} (NYC=100)`);
            $('#sffc-loc1-pp').text(loc1Data.purchasing_power);
            this.renderQoLMetrics('#sffc-loc1-qol', loc1Data.quality_of_life);

            // Update location 2
            $('#sffc-loc2-name').text(this.formatLocationLabel(loc2));
            $('#sffc-loc2-gross').text(loc2Data.formatted?.gross_range || '--');
            $('#sffc-loc2-net').text(loc2Data.formatted?.net_range || '--');
            $('#sffc-loc2-tax').text(`${loc2Data.tax_rate}%`);
            $('#sffc-loc2-col').text(`${loc2Data.cost_of_living} (NYC=100)`);
            $('#sffc-loc2-pp').text(loc2Data.purchasing_power);
            this.renderQoLMetrics('#sffc-loc2-qol', loc2Data.quality_of_life);

            // Update comparison
            if (comparison) {
                this.renderComparisonDiff('#sffc-diff-gross', comparison.gross_difference, loc2);
                this.renderComparisonDiff('#sffc-diff-net', comparison.net_difference, loc2);
                this.renderComparisonDiff('#sffc-diff-pp', comparison.purchasing_power_difference, loc2);
                $('#sffc-comparison-insight').text(comparison.insight || '');
            }
        },

        /**
         * Render QoL metrics for a location
         */
        renderQoLMetrics: function(selector, qol) {
            const $container = $(selector);
            $container.empty();

            if (!qol) return;

            const metrics = [
                { label: 'Score', value: qol.score, icon: 'star' },
                { label: 'Commute', value: qol.commute },
                { label: 'Work-Life', value: qol.work_life },
                { label: 'Culture', value: qol.culture },
            ];

            metrics.forEach(metric => {
                $container.append(`
                    <div class="sffc-qol-metric">
                        <span class="sffc-qol-label">${metric.label}</span>
                        <span class="sffc-qol-value">${metric.value}</span>
                    </div>
                `);
            });
        },

        /**
         * Render comparison difference value
         */
        renderComparisonDiff: function(selector, diff, loc2) {
            const $el = $(selector);
            const prefix = diff > 0 ? '+' : '';
            const className = diff > 0 ? 'diff-positive' : diff < 0 ? 'diff-negative' : 'diff-neutral';

            $el.removeClass('diff-positive diff-negative diff-neutral').addClass(className);
            $el.text(`${prefix}${diff}%`);
        },

        /**
         * Render salary trends
         */
        renderSalaryTrends: function(trends) {
            if (!trends) return;

            // Update outlook badge
            const outlookClass = {
                'strong': 'outlook-strong',
                'positive': 'outlook-positive',
                'stable': 'outlook-stable',
                'improving': 'outlook-improving',
                'recovering': 'outlook-recovering',
                'uncertain': 'outlook-uncertain'
            }[trends.outlook] || 'outlook-stable';

            $('#sffc-trends-outlook .sffc-outlook-badge')
                .removeClass('outlook-strong outlook-positive outlook-stable outlook-improving outlook-recovering outlook-uncertain')
                .addClass(outlookClass)
                .text(trends.outlook ? trends.outlook.charAt(0).toUpperCase() + trends.outlook.slice(1) : '--');

            $('#sffc-trends-industry').text(trends.industry || '--');

            // Render projections
            const $projections = $('#sffc-trends-projections');
            $projections.empty();

            if (trends.projections && trends.projections.length > 0) {
                const symbol = this.getCurrencySymbol(trends.currency);
                trends.projections.forEach(proj => {
                    const growthClass = proj.growth > 3 ? 'growth-high' : proj.growth > 0 ? 'growth-moderate' : 'growth-low';
                    $projections.append(`
                        <div class="sffc-projection-item">
                            <span class="sffc-projection-year">${proj.year}</span>
                            <span class="sffc-projection-salary">${symbol}${this.formatNumber(proj.projected)}</span>
                            <span class="sffc-projection-growth ${growthClass}">+${proj.growth}%</span>
                        </div>
                    `);
                });
            }

            // Update insight
            $('#sffc-trends-insight').text(trends.insight || '');
        },

        /**
         * Render top quartile tips
         */
        renderTopQuartileTips: function(tips) {
            const $list = $('#sffc-top-quartile-tips');
            $list.empty();

            if (!tips || tips.length === 0) {
                $list.html('<p class="sffc-no-data">Complete your profile for personalized tips</p>');
                return;
            }

            tips.forEach(tip => {
                const typeIcon = {
                    'skill': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                    'certification': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18"/></svg>',
                    'experience': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>',
                    'location': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>',
                    'negotiation': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
                    'maintain': '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
                }[tip.type] || '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';

                $list.append(`
                    <div class="sffc-tip-item">
                        <span class="sffc-tip-icon">${typeIcon}</span>
                        <div class="sffc-tip-content">
                            <span class="sffc-tip-title">${this.escapeHtml(tip.title)}</span>
                            <p class="sffc-tip-desc">${this.escapeHtml(tip.description)}</p>
                            <span class="sffc-tip-impact">${this.escapeHtml(tip.impact)}</span>
                        </div>
                    </div>
                `);
            });
        },

        /**
         * Render industry salary chart
         */
        renderIndustrySalaryChart: function(data) {
            const canvas = document.getElementById('sffc-industry-salary-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');

            if (this.charts.industrySalary) {
                this.charts.industrySalary.destroy();
            }

            const industries = (data || []).map(d => d.industry);
            const medians = (data || []).map(d => d.median);

            this.charts.industrySalary = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: industries,
                    datasets: [{
                        label: 'Median Salary',
                        data: medians,
                        backgroundColor: '#059669',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            grid: { color: '#e2e8f0' },
                            ticks: {
                                font: { size: 10 },
                                callback: (value) => this.formatNumber(value)
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        },

        /**
         * Switch chart series
         */
        switchSeries: function(series) {
            this.currentSeries = series;

            $('.sffc-toggle-btn').removeClass('active');
            $(`.sffc-toggle-btn[data-series="${series}"]`).addClass('active');

            $('.sffc-trends-section .sffc-chart-loading').removeClass('hidden');
            this.loadTrends();
        },

        /**
         * Switch date range
         */
        switchRange: function(range) {
            this.currentRange = range;

            $('.sffc-range-btn').removeClass('active');
            $(`.sffc-range-btn[data-range="${range}"]`).addClass('active');

            $('.sffc-trends-section .sffc-chart-loading').removeClass('hidden');
            this.loadTrends();
        },

        /**
         * Export chart as image
         */
        exportChart: function(chartId, filename) {
            const chart = this.charts[chartId];
            if (!chart) {
                console.error('Chart not found:', chartId);
                return;
            }

            const canvas = chart.canvas;
            const link = document.createElement('a');
            link.download = (filename || chartId) + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();

            this.showSuccessToast('Chart exported successfully');
        },

        /**
         * Export trends chart
         */
        exportTrendsChart: function() {
            this.exportChart('trends', 'career-trends-' + this.currentSeries + '-' + this.currentRange);
        },

        /**
         * Export skills chart
         */
        exportSkillsChart: function() {
            this.exportChart('skills', 'skills-analysis');
        },

        /**
         * Update salary comparison when locations change
         */
        updateSalaryComparison: function() {
            this.loadSalaryData();
        },

        /**
         * Refresh entire dashboard
         */
        refreshDashboard: function() {
            const $btn = $('#sffc-refresh-dashboard');
            $btn.addClass('sffc-spinning');

            this.loadInitialData().finally(() => {
                $btn.removeClass('sffc-spinning');
            });
        },

        /**
         * Save user preference
         */
        savePreference: function(name, value) {
            const preferences = {};
            preferences[name] = value;

            this.ajaxRequest('sffc_dashboard_save_preferences', { preferences });
        },

        /**
         * Open settings modal
         */
        openSettings: function() {
            // Scroll to settings section
            $('html, body').animate({
                scrollTop: $('.sffc-settings-section').offset().top - 100
            }, 500);
        },

        /**
         * Open profile editor modal
         */
        openProfileEditor: function() {
            // Create modal if it doesn't exist
            if (!$('#sffc-profile-editor-modal').length) {
                this.createProfileEditorModal();
            }

            // Populate with current data
            this.populateProfileEditor();

            // Show modal
            $('#sffc-profile-editor-modal').addClass('sffc-modal-open');
            $('body').addClass('sffc-modal-body-open');
        },

        /**
         * Create the profile editor modal HTML
         */
        createProfileEditorModal: function() {
            const modalHtml = `
                <div id="sffc-profile-editor-modal" class="sffc-modal">
                    <div class="sffc-modal-overlay"></div>
                    <div class="sffc-modal-container">
                        <div class="sffc-modal-header">
                            <h2>Edit Your Profile</h2>
                            <button class="sffc-modal-close" aria-label="Close">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                        <div class="sffc-modal-body">
                            <form id="sffc-profile-editor-form">
                                <div class="sffc-form-grid">
                                    <div class="sffc-form-group">
                                        <label for="sffc-edit-full-name">Full Name</label>
                                        <input type="text" id="sffc-edit-full-name" name="full_name" class="sffc-input">
                                    </div>
                                    <div class="sffc-form-group">
                                        <label for="sffc-edit-current-role">Current Role</label>
                                        <input type="text" id="sffc-edit-current-role" name="current_role" class="sffc-input" placeholder="e.g., Finance Manager">
                                    </div>
                                    <div class="sffc-form-group">
                                        <label for="sffc-edit-experience">Years of Experience</label>
                                        <select id="sffc-edit-experience" name="years_experience" class="sffc-select">
                                            <option value="">Select...</option>
                                            <option value="0-2">0-2 years</option>
                                            <option value="3-5">3-5 years</option>
                                            <option value="6-10">6-10 years</option>
                                            <option value="11-15">11-15 years</option>
                                            <option value="16+">16+ years</option>
                                        </select>
                                    </div>
                                    <div class="sffc-form-group">
                                        <label for="sffc-edit-seniority">Target Seniority</label>
                                        <select id="sffc-edit-seniority" name="target_seniority" class="sffc-select">
                                            <option value="">Select...</option>
                                            <option value="analyst">Manager</option>
                                            <option value="associate">Associate</option>
                                            <option value="vp">Vice President</option>
                                            <option value="director">Director</option>
                                            <option value="md">Managing Director</option>
                                            <option value="partner">Partner</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="sffc-form-group sffc-form-group-full">
                                    <label>Preferred Industries</label>
                                    <div class="sffc-tags-input" id="sffc-edit-industries">
                                        <div class="sffc-tags-list"></div>
                                        <select class="sffc-tags-add">
                                            <option value="">Add industry...</option>
                                            <option value="Asset Management">Asset Management</option>
                                            <option value="Private Equity">Private Equity</option>
                                            <option value="Investment Banking">Investment Banking</option>
                                            <option value="Private Credit">Private Credit</option>
                                            <option value="Venture Capital">Venture Capital</option>
                                            <option value="Wealth Management">Wealth Management</option>
                                            <option value="FinTech">FinTech</option>
                                            <option value="Consulting">Consulting</option>
                                            <option value="Corporate Finance">Corporate Finance</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="sffc-form-group sffc-form-group-full">
                                    <label>Preferred Locations</label>
                                    <div class="sffc-tags-input" id="sffc-edit-locations">
                                        <div class="sffc-tags-list"></div>
                                        <select class="sffc-tags-add">
                                            <option value="">Add location...</option>
                                            <option value="Dubai">Dubai</option>
                                            <option value="Abu Dhabi">Abu Dhabi</option>
                                            <option value="Riyadh">Riyadh</option>
                                            <option value="Cairo">Cairo</option>
                                            <option value="Doha">Doha</option>
                                            <option value="Manama">Manama</option>
                                            <option value="Jeddah">Jeddah</option>
                                            <option value="Muscat">Muscat</option>
                                            <option value="Kuwait City">Kuwait City</option>
                                            <option value="United Arab Emirates">United Arab Emirates</option>
                                            <option value="Saudi Arabia">Saudi Arabia</option>
                                            <option value="Egypt">Egypt</option>
                                            <option value="Qatar">Qatar</option>
                                            <option value="Bahrain">Bahrain</option>
                                            <option value="Sydney">Sydney</option>
                                            <option value="Toronto">Toronto</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="sffc-form-group sffc-form-group-full">
                                    <label>Skills</label>
                                    <div class="sffc-tags-input" id="sffc-edit-skills">
                                        <div class="sffc-tags-list"></div>
                                        <input type="text" class="sffc-tags-text-input" placeholder="Type a skill and press Enter">
                                    </div>
                                </div>

                                <div class="sffc-form-group sffc-form-group-full">
                                    <label>Work Preference</label>
                                    <div class="sffc-radio-group">
                                        <label class="sffc-radio-option">
                                            <input type="radio" name="work_preference" value="remote">
                                            <span>Remote</span>
                                        </label>
                                        <label class="sffc-radio-option">
                                            <input type="radio" name="work_preference" value="hybrid">
                                            <span>Hybrid</span>
                                        </label>
                                        <label class="sffc-radio-option">
                                            <input type="radio" name="work_preference" value="office">
                                            <span>Office</span>
                                        </label>
                                        <label class="sffc-radio-option">
                                            <input type="radio" name="work_preference" value="flexible">
                                            <span>Flexible</span>
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="sffc-modal-footer">
                            <button class="sffc-btn sffc-btn-outline" id="sffc-profile-cancel">Cancel</button>
                            <button class="sffc-btn sffc-btn-primary" id="sffc-profile-save">
                                <span class="sffc-btn-text">Save Changes</span>
                                <span class="sffc-btn-loading hidden">
                                    <svg class="sffc-spinner" width="20" height="20" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" stroke-linecap="round"/>
                                    </svg>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Bind modal events
            this.bindProfileEditorEvents();
        },

        /**
         * Bind profile editor modal events
         */
        bindProfileEditorEvents: function() {
            const self = this;

            // Close modal
            $(document).on('click', '.sffc-modal-close, .sffc-modal-overlay, #sffc-profile-cancel', function() {
                self.closeProfileEditor();
            });

            // Save profile
            $(document).on('click', '#sffc-profile-save', function() {
                self.saveProfileChanges();
            });

            // Tags input - select
            $(document).on('change', '.sffc-tags-add', function() {
                const value = $(this).val();
                if (value) {
                    self.addTag($(this).closest('.sffc-tags-input'), value);
                    $(this).val('');
                }
            });

            // Tags input - text input
            $(document).on('keypress', '.sffc-tags-text-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = $(this).val().trim();
                    if (value) {
                        self.addTag($(this).closest('.sffc-tags-input'), value);
                        $(this).val('');
                    }
                }
            });

            // Remove tag
            $(document).on('click', '.sffc-tag-remove', function() {
                $(this).closest('.sffc-tag').remove();
            });

            // Close on escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#sffc-profile-editor-modal').hasClass('sffc-modal-open')) {
                    self.closeProfileEditor();
                }
            });
        },

        /**
         * Populate profile editor with current data
         */
        populateProfileEditor: function() {
            // Get current values from the displayed profile
            const currentRole = $('[data-field="current_role"]').text();
            const experience = $('[data-field="years_experience"]').text();
            const industries = $('[data-field="preferred_industries"]').text();
            const locations = $('[data-field="preferred_locations"]').text();
            const skills = $('[data-field="skills"]').text();

            // Set form values
            if (currentRole && currentRole !== 'Not set') {
                $('#sffc-edit-current-role').val(currentRole);
            }

            if (experience && experience !== 'Not set') {
                $('#sffc-edit-experience').val(this.normalizeExperience(experience));
            }

            // Clear and populate tags
            this.clearTags('#sffc-edit-industries');
            this.clearTags('#sffc-edit-locations');
            this.clearTags('#sffc-edit-skills');

            if (industries && industries !== 'Not set') {
                industries.split(',').forEach(item => {
                    if (item.trim()) {
                        this.addTag($('#sffc-edit-industries'), item.trim());
                    }
                });
            }

            if (locations && locations !== 'Not set') {
                locations.split(',').forEach(item => {
                    if (item.trim()) {
                        this.addTag($('#sffc-edit-locations'), item.trim());
                    }
                });
            }

            if (skills && skills !== 'Not set') {
                skills.split(',').forEach(item => {
                    if (item.trim()) {
                        this.addTag($('#sffc-edit-skills'), item.trim());
                    }
                });
            }
        },

        /**
         * Add a tag to a tags input
         */
        addTag: function($container, value) {
            const $list = $container.find('.sffc-tags-list');

            // Check if tag already exists
            const exists = $list.find('.sffc-tag').filter(function() {
                return $(this).data('value').toLowerCase() === value.toLowerCase();
            }).length > 0;

            if (exists) return;

            const tagHtml = `
                <span class="sffc-tag" data-value="${this.escapeHtml(value)}">
                    ${this.escapeHtml(value)}
                    <button type="button" class="sffc-tag-remove" aria-label="Remove">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </span>
            `;

            $list.append(tagHtml);
        },

        /**
         * Clear all tags from container
         */
        clearTags: function(selector) {
            $(selector).find('.sffc-tags-list').empty();
        },

        /**
         * Get tags from container
         */
        getTags: function($container) {
            const tags = [];
            $container.find('.sffc-tag').each(function() {
                tags.push($(this).data('value'));
            });
            return tags;
        },

        /**
         * Save profile changes
         */
        saveProfileChanges: function() {
            const $saveBtn = $('#sffc-profile-save');
            $saveBtn.find('.sffc-btn-text').addClass('hidden');
            $saveBtn.find('.sffc-btn-loading').removeClass('hidden');
            $saveBtn.prop('disabled', true);

            // Collect form data
            const profileData = {
                full_name: $('#sffc-edit-full-name').val(),
                current_role: $('#sffc-edit-current-role').val(),
                years_experience: $('#sffc-edit-experience').val(),
                target_seniority: $('#sffc-edit-seniority').val(),
                preferred_industries: this.getTags($('#sffc-edit-industries')),
                preferred_locations: this.getTags($('#sffc-edit-locations')),
                skills: this.getTags($('#sffc-edit-skills')),
                work_preference: $('input[name="work_preference"]:checked').val()
            };

            // Save each field
            const savePromises = [];

            Object.keys(profileData).forEach(field => {
                const value = profileData[field];
                if (value !== undefined && value !== '' && (Array.isArray(value) ? value.length > 0 : true)) {
                    savePromises.push(
                        this.ajaxRequest('sffc_dashboard_update_profile', {
                            field: field,
                            value: value
                        })
                    );
                }
            });

            Promise.all(savePromises)
                .then(() => {
                    // Update displayed values
                    this.updateDisplayedProfile(profileData);

                    // Refresh stats to reflect changes
                    this.loadStats();

                    // Close modal
                    this.closeProfileEditor();

                    // Show success message
                    this.showSuccessToast('Profile updated successfully');
                })
                .catch((error) => {
                    console.error('Failed to save profile:', error);
                    this.showError('Failed to save changes');
                })
                .finally(() => {
                    $saveBtn.find('.sffc-btn-text').removeClass('hidden');
                    $saveBtn.find('.sffc-btn-loading').addClass('hidden');
                    $saveBtn.prop('disabled', false);
                });
        },

        /**
         * Update the displayed profile values
         */
        updateDisplayedProfile: function(data) {
            if (data.current_role) {
                $('[data-field="current_role"]').text(data.current_role);
            }
            if (data.years_experience) {
                $('[data-field="years_experience"]').text(data.years_experience + ' years');
            }
            if (data.preferred_industries && data.preferred_industries.length) {
                $('[data-field="preferred_industries"]').text(data.preferred_industries.join(', '));
            }
            if (data.preferred_locations && data.preferred_locations.length) {
                $('[data-field="preferred_locations"]').text(data.preferred_locations.join(', '));
            }
            if (data.skills && data.skills.length) {
                $('[data-field="skills"]').text(data.skills.join(', '));
            }
        },

        /**
         * Close profile editor modal
         */
        closeProfileEditor: function() {
            $('#sffc-profile-editor-modal').removeClass('sffc-modal-open');
            $('body').removeClass('sffc-modal-body-open');
        },

        /**
         * Normalize experience string for select
         */
        normalizeExperience: function(exp) {
            if (exp.includes('0-2') || exp.includes('0 -') || exp.includes('1') || exp.includes('2')) {
                return '0-2';
            }
            if (exp.includes('3-5') || exp.includes('3') || exp.includes('4') || exp.includes('5')) {
                return '3-5';
            }
            if (exp.includes('6-10') || exp.includes('6') || exp.includes('7') || exp.includes('8') || exp.includes('9') || exp.includes('10')) {
                return '6-10';
            }
            if (exp.includes('11-15') || exp.includes('11') || exp.includes('12') || exp.includes('13') || exp.includes('14') || exp.includes('15')) {
                return '11-15';
            }
            if (exp.includes('16') || exp.includes('17') || exp.includes('18') || exp.includes('19') || exp.includes('20') || exp.includes('+')) {
                return '16+';
            }
            return '';
        },

        /**
         * Show success toast
         */
        showSuccessToast: function(message) {
            const toastHtml = `
                <div class="sffc-toast sffc-toast-success">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span>${this.escapeHtml(message)}</span>
                </div>
            `;

            const $toast = $(toastHtml).appendTo('body');

            setTimeout(() => {
                $toast.addClass('sffc-toast-visible');
            }, 10);

            setTimeout(() => {
                $toast.removeClass('sffc-toast-visible');
                setTimeout(() => $toast.remove(), 300);
            }, 3000);
        },

        /**
         * Make AJAX request
         */
        ajaxRequest: function(action, data = {}) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: sffcDashboard.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: action,
                        nonce: sffcDashboard.nonce,
                        ...data
                    },
                    success: resolve,
                    error: reject
                });
            });
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            this.isLoading = true;
            $('.sffc-chart-loading').removeClass('hidden');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            this.isLoading = false;
            $('.sffc-chart-loading').addClass('hidden');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            console.error(message);
            // TODO: Implement toast notification
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            if (!num) return '0';
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Get currency symbol
         */
        getCurrencySymbol: function(currency) {
            const symbols = {
                'GBP': '\u00A3',
                'USD': '$',
                'EUR': '\u20AC',
                'SGD': 'S$',
                'HKD': 'HK$',
                'AED': 'AED ',
                'CHF': 'CHF '
            };
            return symbols[currency] || currency + ' ';
        },

        /**
         * Format location label
         */
        formatLocationLabel: function(location) {
            return location.split('-').map(word =>
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },

        // =====================================================
        // SETTINGS & PREFERENCES METHODS
        // =====================================================

        /**
         * Initialize settings functionality
         */
        initSettings: function() {
            this.settingsChanged = false;
            this.originalSettings = this.collectCurrentSettings();
            this.bindSettingsEvents();
            this.initSortableSections();
        },

        /**
         * Bind settings-related events
         */
        bindSettingsEvents: function() {
            const self = this;

            // Settings tabs
            $('.sffc-settings-tab').on('click', function() {
                const tab = $(this).data('tab');
                self.switchSettingsTab(tab);
            });

            // Theme selection
            $('.sffc-theme-option').on('click', function() {
                const theme = $(this).data('theme');
                self.selectTheme(theme);
            });

            // Accent color selection
            $('.sffc-color-btn').on('click', function() {
                const color = $(this).data('color');
                self.selectAccentColor(color);
            });

            // Section visibility toggles
            $('#sffc-section-toggles input').on('change', function() {
                const section = $(this).data('section');
                const visible = $(this).is(':checked');
                self.toggleSectionVisibility(section, visible);
            });

            // Display options toggles
            $('.sffc-display-options input, .sffc-accessibility-options input').on('change', function() {
                self.markSettingsChanged();
            });

            // Chart type selector
            $('select[name="default_chart_type"]').on('change', function() {
                self.markSettingsChanged();
            });

            // Notification settings
            $('input[name="email_enabled"]').on('change', function() {
                const enabled = $(this).is(':checked');
                $('.sffc-digest-settings').toggleClass('disabled', !enabled);
                self.markSettingsChanged();
            });

            $('input[name="quiet_hours_enabled"]').on('change', function() {
                const enabled = $(this).is(':checked');
                $('.sffc-quiet-hours-config').toggleClass('disabled', !enabled);
                self.markSettingsChanged();
            });

            // All other settings inputs
            $('.sffc-settings-content input, .sffc-settings-content select').on('change', function() {
                self.markSettingsChanged();
            });

            // Reset preferences
            $('#sffc-reset-preferences').on('click', () => this.resetPreferences());

            // Save settings
            $('#sffc-save-settings').on('click', () => this.saveAllSettings());

            // Discard changes
            $('#sffc-discard-changes').on('click', () => this.discardChanges());

            // Export data
            $('#sffc-export-data').on('click', () => this.exportUserData());

            // Delete data
            $('#sffc-delete-data').on('click', () => this.openDeleteDataModal());

            // Delete modal events
            $('#sffc-delete-confirm-input').on('input', function() {
                const value = $(this).val();
                $('#sffc-confirm-delete').prop('disabled', value !== 'DELETE');
            });

            $('#sffc-confirm-delete').on('click', () => this.confirmDeleteData());

            // Modal close buttons
            $('#sffc-delete-data-modal .sffc-modal-close, #sffc-delete-data-modal .sffc-modal-cancel').on('click', function() {
                $('#sffc-delete-data-modal').removeClass('active');
            });

            $('#sffc-delete-data-modal .sffc-modal-overlay').on('click', function() {
                $('#sffc-delete-data-modal').removeClass('active');
            });
        },

        /**
         * Switch settings tab
         */
        switchSettingsTab: function(tab) {
            $('.sffc-settings-tab').removeClass('active');
            $(`.sffc-settings-tab[data-tab="${tab}"]`).addClass('active');

            $('.sffc-tab-content').removeClass('active');
            $(`.sffc-tab-content[data-tab="${tab}"]`).addClass('active');
        },

        /**
         * Select theme
         */
        selectTheme: function(theme) {
            $('.sffc-theme-option').removeClass('active');
            $(`.sffc-theme-option[data-theme="${theme}"]`).addClass('active');
            $(`input[name="theme"][value="${theme}"]`).prop('checked', true);

            // Apply theme preview
            this.applyTheme(theme);
            this.markSettingsChanged();
        },

        /**
         * Apply theme to dashboard
         */
        applyTheme: function(theme) {
            const dashboard = $('.sffc-career-dashboard');
            dashboard.removeClass('theme-light theme-dark theme-auto');
            dashboard.addClass('theme-' + theme);

            // If auto, detect system preference
            if (theme === 'auto') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                dashboard.attr('data-effective-theme', prefersDark ? 'dark' : 'light');
            } else {
                dashboard.attr('data-effective-theme', theme);
            }
        },

        /**
         * Select accent color
         */
        selectAccentColor: function(color) {
            $('.sffc-color-btn').removeClass('active').html('');
            const selected = $(`.sffc-color-btn[data-color="${color}"]`);
            selected.addClass('active');
            selected.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>');

            // Apply accent color
            document.documentElement.style.setProperty('--sffc-accent', color);
            document.documentElement.style.setProperty('--sffc-accent-light', color + '20');

            this.markSettingsChanged();
        },

        /**
         * Toggle section visibility
         */
        toggleSectionVisibility: function(section, visible) {
            // Update sortable list indicator
            const item = $(`.sffc-sortable-item[data-section="${section}"]`);
            const indicator = item.find('.sffc-visibility-indicator');
            indicator.removeClass('visible hidden').addClass(visible ? 'visible' : 'hidden');

            if (visible) {
                indicator.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>');
            } else {
                indicator.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>');
            }

            this.markSettingsChanged();
        },

        /**
         * Initialize sortable sections (drag & drop)
         */
        initSortableSections: function() {
            const self = this;
            const sortableList = document.getElementById('sffc-sortable-sections');

            if (!sortableList) return;

            // Simple drag and drop without external library
            let draggedItem = null;

            $(sortableList).on('dragstart', '.sffc-sortable-item', function(e) {
                draggedItem = this;
                $(this).addClass('dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            $(sortableList).on('dragend', '.sffc-sortable-item', function() {
                $(this).removeClass('dragging');
                self.markSettingsChanged();
            });

            $(sortableList).on('dragover', function(e) {
                e.preventDefault();
                const afterElement = self.getDragAfterElement(sortableList, e.originalEvent.clientY);
                if (afterElement === null) {
                    sortableList.appendChild(draggedItem);
                } else {
                    sortableList.insertBefore(draggedItem, afterElement);
                }
            });

            // Make items draggable
            $('.sffc-sortable-item').attr('draggable', 'true');
        },

        /**
         * Get element to insert dragged item after
         */
        getDragAfterElement: function(container, y) {
            const draggableElements = [...container.querySelectorAll('.sffc-sortable-item:not(.dragging)')];

            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        },

        /**
         * Mark settings as changed
         */
        markSettingsChanged: function() {
            this.settingsChanged = true;
            $('#sffc-settings-save-bar').fadeIn(200);
        },

        /**
         * Collect current settings state
         */
        collectCurrentSettings: function() {
            const settings = {
                dashboard: {
                    theme: $('input[name="theme"]:checked').val() || 'light',
                    accent_color: $('.sffc-color-btn.active').data('color') || '#6366f1',
                    compact_mode: $('input[name="compact_mode"]').is(':checked'),
                    animation_enabled: $('input[name="animation_enabled"]').is(':checked'),
                    auto_refresh: $('input[name="auto_refresh"]').is(':checked'),
                    show_welcome: $('input[name="show_welcome"]').is(':checked'),
                    default_chart_type: $('select[name="default_chart_type"]').val()
                },
                sections: {},
                notifications: {
                    email_enabled: $('input[name="email_enabled"]').is(':checked'),
                    email_digest: $('input[name="email_digest"]:checked').val() || 'weekly',
                    digest_day: $('select[name="digest_day"]').val(),
                    digest_time: $('select[name="digest_time"]').val(),
                    alerts: {
                        new_jobs: $('input[name="alert_new_jobs"]').is(':checked'),
                        salary_changes: $('input[name="alert_salary_changes"]').is(':checked'),
                        skill_updates: $('input[name="alert_skill_updates"]').is(':checked'),
                        market_alerts: $('input[name="alert_market_alerts"]').is(':checked'),
                        news_digest: $('input[name="alert_news_digest"]').is(':checked'),
                        learning_reminders: $('input[name="alert_learning_reminders"]').is(':checked'),
                        certification_expiry: $('input[name="alert_certification_expiry"]').is(':checked')
                    },
                    quiet_hours: {
                        enabled: $('input[name="quiet_hours_enabled"]').is(':checked'),
                        start: $('select[name="quiet_start"]').val(),
                        end: $('select[name="quiet_end"]').val(),
                        timezone: $('select[name="quiet_timezone"]').val()
                    }
                },
                privacy: {
                    profile_visibility: $('input[name="profile_visibility"]:checked').val() || 'private',
                    show_in_directory: $('input[name="show_in_directory"]').is(':checked'),
                    allow_recruiter_contact: $('input[name="allow_recruiter_contact"]').is(':checked'),
                    share_anonymous_data: $('input[name="share_anonymous_data"]').is(':checked'),
                    activity_tracking: $('input[name="activity_tracking"]').is(':checked'),
                    personalized_recommendations: $('input[name="personalized_recommendations"]').is(':checked')
                },
                accessibility: {
                    high_contrast: $('input[name="high_contrast"]').is(':checked'),
                    large_text: $('input[name="large_text"]').is(':checked'),
                    reduce_motion: $('input[name="reduce_motion"]').is(':checked'),
                    screen_reader_hints: $('input[name="screen_reader_hints"]').is(':checked')
                }
            };

            // Collect section order and visibility
            $('#sffc-sortable-sections .sffc-sortable-item').each(function(index) {
                const sectionId = $(this).data('section');
                const visible = $(`#sffc-section-toggles input[data-section="${sectionId}"]`).is(':checked');
                settings.sections[sectionId] = {
                    id: sectionId,
                    order: index + 1,
                    visible: visible
                };
            });

            return settings;
        },

        /**
         * Save all settings
         */
        saveAllSettings: function() {
            const self = this;
            const settings = this.collectCurrentSettings();

            $('#sffc-save-settings').prop('disabled', true).text('Saving...');

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_save_preferences',
                    nonce: sffcDashboard.nonce,
                    preferences: JSON.stringify(settings)
                },
                success: function(response) {
                    if (response.success) {
                        self.settingsChanged = false;
                        self.originalSettings = settings;
                        $('#sffc-settings-save-bar').fadeOut(200);
                        self.showNotification('Settings saved successfully', 'success');

                        // Apply any immediate changes
                        self.applySettings(settings);
                    } else {
                        self.showNotification(response.data.message || 'Failed to save settings', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error saving settings', 'error');
                },
                complete: function() {
                    $('#sffc-save-settings').prop('disabled', false).text('Save Changes');
                }
            });
        },

        /**
         * Apply settings immediately
         */
        applySettings: function(settings) {
            // Apply theme
            if (settings.dashboard && settings.dashboard.theme) {
                this.applyTheme(settings.dashboard.theme);
            }

            // Apply accent color
            if (settings.dashboard && settings.dashboard.accent_color) {
                document.documentElement.style.setProperty('--sffc-accent', settings.dashboard.accent_color);
            }

            // Apply accessibility settings
            const dashboard = $('.sffc-career-dashboard');
            dashboard.toggleClass('large-text', settings.accessibility?.large_text || false);
            dashboard.toggleClass('high-contrast', settings.accessibility?.high_contrast || false);
            dashboard.toggleClass('reduce-motion', settings.accessibility?.reduce_motion || false);
        },

        /**
         * Discard changes
         */
        discardChanges: function() {
            // Restore original settings
            location.reload(); // Simple approach - reload page
        },

        /**
         * Reset preferences to defaults
         */
        resetPreferences: function() {
            if (!confirm('Are you sure you want to reset all preferences to defaults?')) {
                return;
            }

            const self = this;

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_reset_preferences',
                    nonce: sffcDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('Preferences reset to defaults', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        self.showNotification('Failed to reset preferences', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error resetting preferences', 'error');
                }
            });
        },

        /**
         * Export user data
         */
        exportUserData: function() {
            const self = this;
            const btn = $('#sffc-export-data');
            btn.prop('disabled', true).html('<svg class="spinning" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="40" stroke-dashoffset="10"/></svg> Exporting...');

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_export_user_data',
                    nonce: sffcDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create and download JSON file
                        const data = JSON.stringify(response.data.data, null, 2);
                        const blob = new Blob([data], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename || 'career-dashboard-export.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        self.showNotification('Data exported successfully', 'success');
                    } else {
                        self.showNotification('Failed to export data', 'error');
                    }
                },
                error: function() {
                    self.showNotification('Error exporting data', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export My Data');
                }
            });
        },

        /**
         * Open delete data modal
         */
        openDeleteDataModal: function() {
            $('#sffc-delete-confirm-input').val('');
            $('#sffc-confirm-delete').prop('disabled', true);
            $('#sffc-delete-data-modal').addClass('active');
        },

        /**
         * Confirm delete data
         */
        confirmDeleteData: function() {
            const self = this;
            const btn = $('#sffc-confirm-delete');
            btn.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_delete_user_data',
                    nonce: sffcDashboard.nonce,
                    confirm: 'DELETE'
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('All your data has been deleted', 'success');
                        if (response.data.redirect) {
                            setTimeout(() => {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        self.showNotification(response.data.message || 'Failed to delete data', 'error');
                        btn.prop('disabled', false).text('Delete Everything');
                    }
                },
                error: function() {
                    self.showNotification('Error deleting data', 'error');
                    btn.prop('disabled', false).text('Delete Everything');
                }
            });
        },

        /**
         * Show notification toast
         */
        showNotification: function(message, type) {
            type = type || 'info';

            // Remove any existing notifications
            $('.sffc-toast').remove();

            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
            };

            const toast = $(`
                <div class="sffc-toast sffc-toast-${type}">
                    ${icons[type] || icons.info}
                    <span>${message}</span>
                    <button class="sffc-toast-close">&times;</button>
                </div>
            `);

            $('body').append(toast);

            setTimeout(() => toast.addClass('show'), 10);

            toast.find('.sffc-toast-close').on('click', () => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            });

            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        },

        // =====================================================
        // PHASE 9: MOBILE OPTIMIZATION & PERFORMANCE
        // =====================================================

        /**
         * Initialize mobile features
         */
        initMobile: function() {
            this.isMobile = window.matchMedia('(max-width: 768px)').matches;
            this.isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

            this.initSwipeableCards();
            this.initCollapsibleSections();
            this.initMobileActionBar();
            this.initPullToRefresh();
            this.initOfflineDetection();

            // Listen for resize events
            window.addEventListener('resize', this.debounce(() => {
                this.isMobile = window.matchMedia('(max-width: 768px)').matches;
                this.updateMobileLayout();
            }, 250));
        },

        /**
         * Initialize swipeable cards on mobile
         */
        initSwipeableCards: function() {
            if (!this.isMobile) return;

            const statsRow = document.querySelector('.sffc-stats-row');
            if (!statsRow) return;

            // Add swipe indicator
            const indicator = document.createElement('div');
            indicator.className = 'sffc-swipe-indicator';
            indicator.innerHTML = `
                <span>Swipe to see more</span>
                <div class="sffc-swipe-dots">
                    <span class="sffc-swipe-dot active"></span>
                    <span class="sffc-swipe-dot"></span>
                    <span class="sffc-swipe-dot"></span>
                </div>
            `;
            statsRow.after(indicator);

            // Update dots on scroll
            statsRow.addEventListener('scroll', this.debounce(() => {
                const scrollLeft = statsRow.scrollLeft;
                const cardWidth = statsRow.querySelector('.sffc-stat-card')?.offsetWidth || 280;
                const activeIndex = Math.round(scrollLeft / cardWidth);

                indicator.querySelectorAll('.sffc-swipe-dot').forEach((dot, i) => {
                    dot.classList.toggle('active', i === activeIndex);
                });
            }, 50));
        },

        /**
         * Initialize collapsible sections on mobile
         */
        initCollapsibleSections: function() {
            if (!this.isMobile) return;

            const sections = document.querySelectorAll('.sffc-dashboard-section');

            sections.forEach(section => {
                const header = section.querySelector('.sffc-section-header');
                const content = section.querySelector('.sffc-section-content') ||
                               section.querySelector('.sffc-settings-grid') ||
                               section.querySelector('.sffc-market-grid') ||
                               section.querySelector('.sffc-skills-content');

                if (!header || !content) return;

                // Add collapsible class
                section.classList.add('sffc-section-collapsible');

                // Create toggle button
                const toggleBtn = document.createElement('button');
                toggleBtn.className = 'sffc-section-toggle';
                toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';
                header.appendChild(toggleBtn);

                // Wrap content if needed
                if (!content.classList.contains('sffc-section-content')) {
                    content.classList.add('sffc-section-content');
                }

                // Toggle handler
                toggleBtn.addEventListener('click', () => {
                    const isCollapsed = content.classList.contains('collapsed');
                    content.classList.toggle('collapsed');
                    toggleBtn.classList.toggle('collapsed');

                    if (isCollapsed) {
                        content.style.maxHeight = content.scrollHeight + 'px';
                        setTimeout(() => content.style.maxHeight = '', 300);
                    } else {
                        content.style.maxHeight = content.scrollHeight + 'px';
                        requestAnimationFrame(() => {
                            content.style.maxHeight = '0';
                        });
                    }
                });
            });
        },

        /**
         * Initialize mobile action bar
         */
        initMobileActionBar: function() {
            if (!this.isMobile) return;

            // Check if action bar already exists
            if (document.querySelector('.sffc-mobile-action-bar')) return;

            const actionBar = document.createElement('div');
            actionBar.className = 'sffc-mobile-action-bar';
            actionBar.innerHTML = `
                <button class="sffc-mobile-action active" data-section="overview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span>Overview</span>
                </button>
                <button class="sffc-mobile-action" data-section="skills">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <span>Skills</span>
                </button>
                <button class="sffc-mobile-action" data-section="salary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <span>Salary</span>
                </button>
                <button class="sffc-mobile-action" data-section="settings">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-3 3l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-3-3l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 3-3l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 3 3l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    <span>Settings</span>
                </button>
            `;

            document.body.appendChild(actionBar);

            // Handle navigation
            actionBar.querySelectorAll('.sffc-mobile-action').forEach(btn => {
                btn.addEventListener('click', () => {
                    const section = btn.dataset.section;
                    this.scrollToSection(section);

                    // Update active state
                    actionBar.querySelectorAll('.sffc-mobile-action').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });

            // Update active state on scroll
            this.observeSections();
        },

        /**
         * Scroll to a specific section
         */
        scrollToSection: function(sectionName) {
            const sectionMap = {
                'overview': '.sffc-stats-row',
                'skills': '.sffc-skills-section',
                'salary': '.sffc-salary-section',
                'settings': '.sffc-settings-section'
            };

            const selector = sectionMap[sectionName];
            const section = document.querySelector(selector);

            if (section) {
                const offset = 20;
                const top = section.getBoundingClientRect().top + window.pageYOffset - offset;
                window.scrollTo({ top, behavior: 'smooth' });
            }
        },

        /**
         * Observe sections for mobile nav highlighting
         */
        observeSections: function() {
            if (!('IntersectionObserver' in window)) return;

            const sections = {
                '.sffc-stats-row': 'overview',
                '.sffc-skills-section': 'skills',
                '.sffc-salary-section': 'salary',
                '.sffc-settings-section': 'settings'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const sectionName = sections[Object.keys(sections).find(sel =>
                            entry.target.matches(sel)
                        )];

                        if (sectionName) {
                            document.querySelectorAll('.sffc-mobile-action').forEach(btn => {
                                btn.classList.toggle('active', btn.dataset.section === sectionName);
                            });
                        }
                    }
                });
            }, { threshold: 0.3 });

            Object.keys(sections).forEach(selector => {
                const el = document.querySelector(selector);
                if (el) observer.observe(el);
            });
        },

        /**
         * Initialize pull to refresh
         */
        initPullToRefresh: function() {
            if (!this.isTouch) return;

            const dashboard = document.querySelector('.sffc-career-dashboard');
            if (!dashboard) return;

            let startY = 0;
            let currentY = 0;
            let isPulling = false;
            const threshold = 80;

            // Create indicator
            const indicator = document.createElement('div');
            indicator.className = 'sffc-ptr-indicator';
            indicator.innerHTML = `
                <svg class="sffc-ptr-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                <span>Pull to refresh</span>
            `;
            dashboard.prepend(indicator);

            dashboard.addEventListener('touchstart', (e) => {
                if (window.scrollY === 0) {
                    startY = e.touches[0].pageY;
                    isPulling = true;
                }
            }, { passive: true });

            dashboard.addEventListener('touchmove', (e) => {
                if (!isPulling) return;

                currentY = e.touches[0].pageY;
                const pullDistance = currentY - startY;

                if (pullDistance > 0 && pullDistance < threshold * 1.5) {
                    indicator.classList.add('pulling');
                    indicator.style.transform = `translateY(${Math.min(pullDistance - 60, 0)}px)`;
                    indicator.querySelector('span').textContent =
                        pullDistance > threshold ? 'Release to refresh' : 'Pull to refresh';
                }
            }, { passive: true });

            dashboard.addEventListener('touchend', () => {
                if (!isPulling) return;

                const pullDistance = currentY - startY;

                if (pullDistance > threshold) {
                    indicator.classList.add('refreshing');
                    indicator.querySelector('span').textContent = 'Refreshing...';

                    this.refreshDashboard().finally(() => {
                        indicator.classList.remove('pulling', 'refreshing');
                        indicator.style.transform = '';
                    });
                } else {
                    indicator.classList.remove('pulling');
                    indicator.style.transform = '';
                }

                isPulling = false;
                startY = 0;
                currentY = 0;
            });
        },

        /**
         * Initialize offline detection
         */
        initOfflineDetection: function() {
            // Create offline banner
            const banner = document.createElement('div');
            banner.className = 'sffc-offline-banner';
            banner.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="1" y1="1" x2="23" y2="23"/>
                    <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                    <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                    <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                    <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                    <line x1="12" y1="20" x2="12.01" y2="20"/>
                </svg>
                <span>You're offline. Some features may be unavailable.</span>
            `;
            document.body.prepend(banner);

            // Check connection status
            const updateOnlineStatus = () => {
                banner.classList.toggle('show', !navigator.onLine);

                if (!navigator.onLine) {
                    this.markDataAsStale();
                }
            };

            window.addEventListener('online', updateOnlineStatus);
            window.addEventListener('offline', updateOnlineStatus);
            updateOnlineStatus();
        },

        /**
         * Mark data as stale when offline
         */
        markDataAsStale: function() {
            const cards = document.querySelectorAll('.sffc-stat-card');
            cards.forEach(card => {
                if (!card.querySelector('.sffc-stale-data-badge')) {
                    const badge = document.createElement('span');
                    badge.className = 'sffc-stale-data-badge';
                    badge.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Cached';
                    card.querySelector('.sffc-stat-header')?.appendChild(badge);
                }
            });
        },

        /**
         * Initialize lazy loading for charts and images
         */
        initLazyLoading: function() {
            if (!('IntersectionObserver' in window)) {
                // Fallback for older browsers
                this.loadAllCharts();
                return;
            }

            const lazyElements = document.querySelectorAll('[data-lazy]');

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const el = entry.target;
                        const type = el.dataset.lazy;

                        if (type === 'chart') {
                            this.loadChart(el);
                        } else if (type === 'image') {
                            this.loadImage(el);
                        }

                        observer.unobserve(el);
                    }
                });
            }, { rootMargin: '100px' });

            lazyElements.forEach(el => observer.observe(el));
        },

        /**
         * Load chart lazily
         */
        loadChart: function(container) {
            const chartId = container.dataset.chartId;
            container.innerHTML = '<div class="sffc-chart-loading"><div class="sffc-chart-loading-spinner"></div></div>';

            // Simulate chart loading (replace with actual chart init)
            setTimeout(() => {
                container.classList.add('sffc-fade-in');
                // Initialize actual chart here based on chartId
            }, 300);
        },

        /**
         * Load image lazily
         */
        loadImage: function(img) {
            const src = img.dataset.src;
            if (!src) return;

            img.classList.add('sffc-lazy-image');

            const newImg = new Image();
            newImg.onload = () => {
                img.src = src;
                img.classList.add('loaded');
            };
            newImg.src = src;
        },

        /**
         * Show skeleton loaders
         */
        showSkeletons: function() {
            const statsRow = document.querySelector('.sffc-stats-row');
            if (statsRow && !statsRow.dataset.loaded) {
                statsRow.innerHTML = `
                    <div class="sffc-skeleton-card sffc-skeleton-stat">
                        <div class="sffc-skeleton sffc-skeleton-header"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-value"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-label"></div>
                    </div>
                    <div class="sffc-skeleton-card sffc-skeleton-stat">
                        <div class="sffc-skeleton sffc-skeleton-header"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-value"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-label"></div>
                    </div>
                    <div class="sffc-skeleton-card sffc-skeleton-stat">
                        <div class="sffc-skeleton sffc-skeleton-header"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-value"></div>
                        <div class="sffc-skeleton sffc-skeleton-stat-label"></div>
                    </div>
                `;
            }
        },

        /**
         * Hide skeleton loaders
         */
        hideSkeletons: function() {
            document.querySelectorAll('.sffc-skeleton-card').forEach(el => {
                el.classList.add('sffc-fade-in');
                setTimeout(() => el.remove(), 300);
            });
        },

        /**
         * Update layout based on screen size
         */
        updateMobileLayout: function() {
            const actionBar = document.querySelector('.sffc-mobile-action-bar');
            const swipeIndicator = document.querySelector('.sffc-swipe-indicator');

            if (this.isMobile) {
                if (actionBar) actionBar.style.display = 'flex';
                if (swipeIndicator) swipeIndicator.style.display = 'block';
            } else {
                if (actionBar) actionBar.style.display = 'none';
                if (swipeIndicator) swipeIndicator.style.display = 'none';
            }
        },

        /**
         * Debounce utility
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Throttle utility
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Preload critical resources
         */
        preloadResources: function() {
            // Preload fonts
            const fontLink = document.createElement('link');
            fontLink.rel = 'preload';
            fontLink.href = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
            fontLink.as = 'style';
            document.head.appendChild(fontLink);

            // Preconnect to API endpoints
            const preconnect = document.createElement('link');
            preconnect.rel = 'preconnect';
            preconnect.href = window.location.origin;
            document.head.appendChild(preconnect);
        },

        /**
         * Cache dashboard data
         */
        cacheData: function(key, data, expiry = 300000) { // 5 min default
            try {
                const item = {
                    data: data,
                    expiry: Date.now() + expiry
                };
                localStorage.setItem('sffc_cache_' + key, JSON.stringify(item));
            } catch (e) {
                console.warn('Cache write failed:', e);
            }
        },

        /**
         * Get cached data
         */
        getCachedData: function(key) {
            try {
                const item = JSON.parse(localStorage.getItem('sffc_cache_' + key));
                if (item && item.expiry > Date.now()) {
                    return item.data;
                }
                localStorage.removeItem('sffc_cache_' + key);
            } catch (e) {
                console.warn('Cache read failed:', e);
            }
            return null;
        },

        // ============================================
        // Quick Edit Modal
        // ============================================

        /**
         * Initialize quick edit modal functionality
         */
        initQuickEdit: function() {
            // Open quick edit modal
            $('#sffc-edit-profile, .sffc-quick-edit-trigger').on('click', () => this.openQuickEditModal());

            // Close modal
            $('#sffc-close-quick-edit, .sffc-quick-edit-cancel').on('click', () => this.closeQuickEditModal());
            $('#sffc-quick-edit-modal').on('click', (e) => {
                if ($(e.target).is('#sffc-quick-edit-modal')) {
                    this.closeQuickEditModal();
                }
            });

            // Tab switching - support both selector patterns
            $('.sffc-edit-tab, .sffc-quick-edit-tab').on('click', (e) => {
                const tabId = $(e.currentTarget).data('tab');
                this.switchQuickEditTab(tabId);
            });

            // Form submission
            $('#sffc-quick-edit-form').on('submit', (e) => {
                e.preventDefault();
                this.saveQuickEditForm();
            });

            // Skills tag management
            this.initSkillsTagManager();

            // Escape key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && $('#sffc-quick-edit-modal').is(':visible')) {
                    this.closeQuickEditModal();
                }
            });
        },

        /**
         * Open quick edit modal
         */
        openQuickEditModal: function() {
            $('#sffc-quick-edit-modal').fadeIn(200);
            $('body').addClass('sffc-modal-open');

            // Focus first input
            setTimeout(() => {
                $('#sffc-quick-edit-form input:visible:first').focus();
            }, 200);
        },

        /**
         * Close quick edit modal
         */
        closeQuickEditModal: function() {
            $('#sffc-quick-edit-modal').fadeOut(200);
            $('body').removeClass('sffc-modal-open');
        },

        /**
         * Switch quick edit tab
         */
        switchQuickEditTab: function(tabId) {
            // Update tab buttons - support both selector patterns
            $('.sffc-edit-tab, .sffc-quick-edit-tab').removeClass('active');
            $(`.sffc-edit-tab[data-tab="${tabId}"], .sffc-quick-edit-tab[data-tab="${tabId}"]`).addClass('active');

            // Update tab panels - support both selector patterns
            $('.sffc-edit-tab-content, .sffc-quick-edit-panel').removeClass('active');
            $(`.sffc-edit-tab-content[data-tab="${tabId}"], #sffc-quick-edit-${tabId}`).addClass('active');
        },

        /**
         * Initialize skills tag manager
         */
        initSkillsTagManager: function() {
            const $input = $('#sffc-skill-input');
            const $addBtn = $('#sffc-add-skill-btn');
            const $container = $('#sffc-skills-tags-container');

            // Add skill on button click
            $addBtn.on('click', () => {
                this.addSkillTag($input.val().trim());
                $input.val('').focus();
            });

            // Add skill on Enter
            $input.on('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.addSkillTag($input.val().trim());
                    $input.val('').focus();
                }
            });

            // Remove skill tag
            $container.on('click', '.sffc-skill-tag-remove', (e) => {
                $(e.currentTarget).closest('.sffc-skill-tag').remove();
                this.updateSkillsHiddenField();
            });
        },

        /**
         * Add a skill tag
         */
        addSkillTag: function(skill) {
            if (!skill) return;

            const $container = $('#sffc-skills-tags-container');

            // Check for duplicates
            const existing = $container.find('.sffc-skill-tag').map(function() {
                return $(this).data('skill').toLowerCase();
            }).get();

            if (existing.includes(skill.toLowerCase())) {
                this.showNotification('Skill already added', 'warning');
                return;
            }

            // Add tag
            $container.append(`
                <span class="sffc-skill-tag" data-skill="${this.escapeHtml(skill)}">
                    ${this.escapeHtml(skill)}
                    <button type="button" class="sffc-skill-tag-remove" aria-label="Remove ${this.escapeHtml(skill)}">×</button>
                </span>
            `);

            this.updateSkillsHiddenField();
        },

        /**
         * Update hidden field with current skills
         */
        updateSkillsHiddenField: function() {
            const skills = $('#sffc-skills-tags-container .sffc-skill-tag').map(function() {
                return $(this).data('skill');
            }).get();

            $('#sffc-skills-hidden').val(JSON.stringify(skills));
        },

        /**
         * Save quick edit form
         */
        saveQuickEditForm: function() {
            const $form = $('#sffc-quick-edit-form');
            const $btn = $form.find('.sffc-quick-edit-save');

            // Update skills hidden field
            this.updateSkillsHiddenField();

            const formData = new FormData($form[0]);
            const data = {};

            formData.forEach((value, key) => {
                if (key.endsWith('[]')) {
                    const cleanKey = key.replace('[]', '');
                    if (!data[cleanKey]) data[cleanKey] = [];
                    data[cleanKey].push(value);
                } else {
                    data[key] = value;
                }
            });

            // Show loading state
            $btn.prop('disabled', true).text('Saving...');

            this.ajaxRequest('sffc_dashboard_save_profile', { profile_data: data })
                .then((response) => {
                    if (response.success) {
                        this.showNotification('Profile saved successfully', 'success');
                        this.closeQuickEditModal();

                        // Refresh dashboard data
                        this.refreshDashboard();

                        // Update profile completion if provided
                        if (response.data && response.data.completion) {
                            this.updateProfileCompletion(response.data.completion);
                        }
                    } else {
                        this.showNotification(response.data?.message || 'Failed to save profile', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to save profile', 'error');
                })
                .finally(() => {
                    $btn.prop('disabled', false).text('Save Changes');
                });
        },

        /**
         * Update profile completion percentage
         */
        updateProfileCompletion: function(percentage) {
            $('.sffc-profile-completion').text(`${percentage}% Complete`);
            $('.sffc-completion-bar-fill').css('width', `${percentage}%`);
        },

        // ============================================
        // Missing Fields Indicator
        // ============================================

        /**
         * Initialize missing fields indicator
         */
        initMissingFields: function() {
            const self = this;

            // Toggle expand/collapse
            $('.sffc-missing-fields-header').on('click', () => this.toggleMissingFields());

            // One-click add buttons
            $('.sffc-add-field-btn').on('click', (e) => {
                e.stopPropagation();
                const field = $(e.currentTarget).data('field');
                this.openQuickEditForField(field);
            });

            // Complete All Fields button - triggers onboarding
            $('#sffc-complete-profile-btn').on('click', (e) => {
                e.stopPropagation();
                self.startProfileOnboarding();
            });

            // Dismiss indicator
            $('.sffc-missing-dismiss').on('click', () => this.dismissMissingFields());

            // Auto-expand on first view if not dismissed
            if (!sessionStorage.getItem('sffc_missing_fields_seen')) {
                setTimeout(() => {
                    $('.sffc-missing-fields-indicator').addClass('expanded');
                    $('#sffc-missing-fields-list').show();
                    sessionStorage.setItem('sffc_missing_fields_seen', 'true');
                }, 2000);
            }
        },

        /**
         * Start profile completion onboarding flow
         */
        startProfileOnboarding: function() {
            // Hide the missing fields indicator
            $('.sffc-missing-fields-indicator').fadeOut(200);

            // Clear any previous onboarding state
            localStorage.removeItem('sffc_onboarding_complete');
            sessionStorage.removeItem('sffc_onboarding_dismissed');

            // Start the onboarding flow
            this.showOnboardingStep(1);
        },

        /**
         * Toggle missing fields expansion
         */
        toggleMissingFields: function() {
            const $indicator = $('.sffc-missing-fields-indicator');
            const $list = $('#sffc-missing-fields-list');

            $indicator.toggleClass('expanded');
            const isExpanded = $indicator.hasClass('expanded');

            if (isExpanded) {
                $list.slideDown(200);
            } else {
                $list.slideUp(200);
            }

            $('.sffc-missing-toggle').attr('aria-expanded', isExpanded);
        },

        /**
         * Open quick edit modal focused on a specific field
         */
        openQuickEditForField: function(field) {
            // Determine which tab contains this field
            const fieldTabMap = {
                // Basic Info tab
                'full_name': 'basic',
                'current_role': 'basic',
                'years_experience': 'basic',
                'education_level': 'basic',
                // Career tab
                'target_seniority': 'career',
                'target_industries': 'career',
                'target_roles': 'career',
                // Preferences tab
                'preferred_locations': 'preferences',
                'work_style': 'preferences',
                'salary_expectation': 'preferences',
                'salary_expectations': 'preferences',
                'availability': 'preferences',
                // Skills tab
                'skills': 'skills',
                'certifications': 'skills'
            };

            const tab = fieldTabMap[field] || 'basic';

            this.openQuickEditModal();
            this.switchQuickEditTab(tab);

            // Focus the specific field after modal opens
            setTimeout(() => {
                $(`#sffc-edit-${field}, #sffc-${field}, [name="${field}"]`).first().focus();
            }, 300);
        },

        /**
         * Dismiss missing fields indicator
         */
        dismissMissingFields: function() {
            $('.sffc-missing-fields-indicator').fadeOut(200);

            // Remember dismissal for this session
            sessionStorage.setItem('sffc_missing_fields_dismissed', 'true');
        },

        // ============================================
        // Onboarding Tooltips
        // ============================================

        /**
         * Initialize onboarding tour
         */
        initOnboarding: function() {
            // Check if tour already completed
            if (localStorage.getItem('sffc_onboarding_complete') === 'true') {
                return;
            }

            // Check if dismissed this session
            if (sessionStorage.getItem('sffc_onboarding_dismissed') === 'true') {
                return;
            }

            // Show first tooltip after a delay
            setTimeout(() => this.showOnboardingStep(1), 1000);

            // Bind navigation buttons
            $(document).on('click', '.sffc-tooltip-next', () => this.nextOnboardingStep());
            $(document).on('click', '.sffc-tooltip-prev', () => this.prevOnboardingStep());
            $(document).on('click', '.sffc-tooltip-skip', () => this.skipOnboarding());
            $(document).on('click', '.sffc-tooltip-finish', () => this.completeOnboarding());

            // Close on escape
            $(document).on('keydown.onboarding', (e) => {
                if (e.key === 'Escape') {
                    this.skipOnboarding();
                }
            });
        },

        /**
         * Current onboarding step
         */
        onboardingStep: 1,
        totalOnboardingSteps: 5,

        /**
         * Show specific onboarding step
         */
        showOnboardingStep: function(step) {
            this.onboardingStep = step;

            // Hide all tooltips
            $('.sffc-onboarding-tooltip').removeClass('active');

            // Show current tooltip
            $(`.sffc-onboarding-tooltip[data-step="${step}"]`).addClass('active');

            // Update progress dots
            this.updateOnboardingProgress(step);

            // Scroll element into view if needed
            this.scrollToOnboardingTarget(step);
        },

        /**
         * Update onboarding progress dots
         */
        updateOnboardingProgress: function(step) {
            $('.sffc-tooltip-dot').removeClass('active completed');

            for (let i = 1; i <= this.totalOnboardingSteps; i++) {
                const $dot = $(`.sffc-tooltip-dot[data-step="${i}"]`);
                if (i < step) {
                    $dot.addClass('completed');
                } else if (i === step) {
                    $dot.addClass('active');
                }
            }
        },

        /**
         * Scroll to onboarding target element
         */
        scrollToOnboardingTarget: function(step) {
            const targets = {
                1: '.sffc-stats-grid',
                2: '.sffc-section[data-section="trends"]',
                3: '.sffc-section[data-section="skills"]',
                4: '.sffc-section[data-section="market"]',
                5: '.sffc-section[data-section="settings"]'
            };

            const target = targets[step];
            if (target && $(target).length) {
                $('html, body').animate({
                    scrollTop: $(target).offset().top - 100
                }, 300);
            }
        },

        /**
         * Go to next onboarding step
         */
        nextOnboardingStep: function() {
            if (this.onboardingStep < this.totalOnboardingSteps) {
                this.showOnboardingStep(this.onboardingStep + 1);
            }
        },

        /**
         * Go to previous onboarding step
         */
        prevOnboardingStep: function() {
            if (this.onboardingStep > 1) {
                this.showOnboardingStep(this.onboardingStep - 1);
            }
        },

        /**
         * Skip onboarding tour
         */
        skipOnboarding: function() {
            $('.sffc-onboarding-tooltip').removeClass('active');
            sessionStorage.setItem('sffc_onboarding_dismissed', 'true');
            $(document).off('keydown.onboarding');
        },

        /**
         * Complete onboarding tour
         */
        completeOnboarding: function() {
            $('.sffc-onboarding-tooltip').removeClass('active');
            localStorage.setItem('sffc_onboarding_complete', 'true');
            $(document).off('keydown.onboarding');

            // Save to server
            this.ajaxRequest('sffc_dashboard_save_preference', {
                preference: 'onboarding_complete',
                value: true
            });

            this.showNotification('Tour completed! You can restart it from Settings.', 'success');
        },

        /**
         * Restart onboarding tour
         */
        restartOnboarding: function() {
            localStorage.removeItem('sffc_onboarding_complete');
            sessionStorage.removeItem('sffc_onboarding_dismissed');
            this.showOnboardingStep(1);
        },

        // ============================================
        // News Sources Management
        // ============================================

        /**
         * Initialize news sources functionality
         */
        initNewsSources: function() {
            // Open news sources modal
            $('.sffc-news-sources-gear, #sffc-manage-sources-btn').on('click', () => this.openNewsSourcesModal());

            // Close modal
            $('#sffc-close-sources-modal').on('click', () => this.closeNewsSourcesModal());
            $('#sffc-news-sources-modal').on('click', (e) => {
                if ($(e.target).is('#sffc-news-sources-modal')) {
                    this.closeNewsSourcesModal();
                }
            });

            // Pin/unpin source
            $(document).on('click', '.sffc-source-pin', (e) => {
                const sourceId = $(e.currentTarget).closest('.sffc-source-item').data('source-id');
                this.toggleSourcePin(sourceId, e.currentTarget);
            });

            // Hide/show source
            $(document).on('click', '.sffc-source-hide', (e) => {
                const sourceId = $(e.currentTarget).closest('.sffc-source-item').data('source-id');
                this.toggleSourceVisibility(sourceId, e.currentTarget);
            });

            // Save preferences
            $('#sffc-save-source-prefs').on('click', () => this.saveSourcePreferences());
        },

        /**
         * Open news sources modal
         */
        openNewsSourcesModal: function() {
            $('#sffc-news-sources-modal').fadeIn(200);
            $('body').addClass('sffc-modal-open');
        },

        /**
         * Close news sources modal
         */
        closeNewsSourcesModal: function() {
            $('#sffc-news-sources-modal').fadeOut(200);
            $('body').removeClass('sffc-modal-open');
        },

        /**
         * Toggle source pin status
         */
        toggleSourcePin: function(sourceId, button) {
            const $btn = $(button);
            const $item = $btn.closest('.sffc-source-item');
            const isPinned = $item.hasClass('pinned');

            $item.toggleClass('pinned');
            $btn.attr('aria-pressed', !isPinned);
            $btn.find('.sffc-icon').toggleClass('filled');
        },

        /**
         * Toggle source visibility
         */
        toggleSourceVisibility: function(sourceId, button) {
            const $btn = $(button);
            const $item = $btn.closest('.sffc-source-item');
            const isHidden = $item.hasClass('hidden');

            $item.toggleClass('hidden');
            $btn.attr('aria-pressed', !isHidden);
        },

        /**
         * Save source preferences
         */
        saveSourcePreferences: function() {
            const preferences = [];

            $('.sffc-source-item').each(function() {
                const $item = $(this);
                preferences.push({
                    source_id: $item.data('source-id'),
                    is_pinned: $item.hasClass('pinned') ? 1 : 0,
                    is_hidden: $item.hasClass('hidden') ? 1 : 0
                });
            });

            const $btn = $('#sffc-save-source-prefs');
            $btn.prop('disabled', true).text('Saving...');

            this.ajaxRequest('sffc_dashboard_save_source_preferences', { preferences: preferences })
                .then((response) => {
                    if (response.success) {
                        this.showNotification('Source preferences saved', 'success');
                        this.closeNewsSourcesModal();

                        // Refresh news feed
                        this.loadMarketIntel($('#sffc-market-filter').val());
                    } else {
                        this.showNotification(response.data?.message || 'Failed to save preferences', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to save preferences', 'error');
                })
                .finally(() => {
                    $btn.prop('disabled', false).text('Save Preferences');
                });
        },

        // ============================================
        // Alert Keywords Management
        // ============================================

        /**
         * Initialize alert keywords functionality
         */
        initAlertKeywords: function() {
            // Toggle add form
            $('#sffc-add-keyword-btn').on('click', () => this.toggleKeywordForm());

            // Save keyword
            $('#sffc-save-keyword-btn').on('click', () => this.saveAlertKeyword());

            // Enter to save
            $('#sffc-keyword-input').on('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveAlertKeyword();
                }
            });

            // Toggle keyword active status
            $(document).on('click', '.sffc-keyword-toggle', (e) => {
                const keywordId = $(e.currentTarget).closest('.sffc-keyword-tag').data('keyword-id');
                this.toggleKeywordActive(keywordId, e.currentTarget);
            });

            // Delete keyword
            $(document).on('click', '.sffc-keyword-delete', (e) => {
                const keywordId = $(e.currentTarget).closest('.sffc-keyword-tag').data('keyword-id');
                this.deleteAlertKeyword(keywordId, e.currentTarget);
            });
        },

        /**
         * Toggle keyword add form visibility
         */
        toggleKeywordForm: function() {
            const $form = $('.sffc-keyword-add-form');
            const $btn = $('#sffc-add-keyword-btn');

            $form.slideToggle(200);

            if ($form.is(':visible')) {
                $('#sffc-keyword-input').focus();
                $btn.html('<span class="sffc-icon">−</span>');
            } else {
                $btn.html('<span class="sffc-icon">+</span>');
            }
        },

        /**
         * Save alert keyword
         */
        saveAlertKeyword: function() {
            const $input = $('#sffc-keyword-input');
            const $type = $('#sffc-keyword-type');
            const keyword = $input.val().trim();
            const type = $type.val();

            if (!keyword) {
                this.showNotification('Please enter a keyword', 'warning');
                $input.focus();
                return;
            }

            const $btn = $('#sffc-save-keyword-btn');
            $btn.prop('disabled', true);

            this.ajaxRequest('sffc_dashboard_add_alert_keyword', {
                keyword: keyword,
                keyword_type: type
            })
                .then((response) => {
                    if (response.success) {
                        // Add keyword to list
                        this.addKeywordToList(response.data);

                        // Clear form
                        $input.val('');

                        this.showNotification('Alert keyword added', 'success');
                    } else {
                        this.showNotification(response.data?.message || 'Failed to add keyword', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to add keyword', 'error');
                })
                .finally(() => {
                    $btn.prop('disabled', false);
                });
        },

        /**
         * Add keyword to the list
         */
        addKeywordToList: function(data) {
            const $list = $('.sffc-keywords-list');

            $list.append(`
                <span class="sffc-keyword-tag active" data-keyword-id="${data.id}">
                    <span class="sffc-keyword-type-indicator" data-type="${this.escapeHtml(data.type)}"></span>
                    ${this.escapeHtml(data.keyword)}
                    <button type="button" class="sffc-keyword-toggle" aria-label="Toggle alert">
                        <span class="sffc-icon">🔔</span>
                    </button>
                    <button type="button" class="sffc-keyword-delete" aria-label="Delete keyword">×</button>
                </span>
            `);
        },

        /**
         * Toggle keyword active status
         */
        toggleKeywordActive: function(keywordId, button) {
            const $tag = $(button).closest('.sffc-keyword-tag');
            const isActive = $tag.hasClass('active');

            this.ajaxRequest('sffc_dashboard_toggle_alert_keyword', {
                keyword_id: keywordId,
                is_active: isActive ? 0 : 1
            })
                .then((response) => {
                    if (response.success) {
                        $tag.toggleClass('active');
                        $(button).find('.sffc-icon').text(isActive ? '🔕' : '🔔');
                    } else {
                        this.showNotification('Failed to update keyword', 'error');
                    }
                });
        },

        /**
         * Delete alert keyword
         */
        deleteAlertKeyword: function(keywordId, button) {
            if (!confirm('Delete this alert keyword?')) {
                return;
            }

            const $tag = $(button).closest('.sffc-keyword-tag');

            this.ajaxRequest('sffc_dashboard_delete_alert_keyword', { keyword_id: keywordId })
                .then((response) => {
                    if (response.success) {
                        $tag.fadeOut(200, () => $tag.remove());
                        this.showNotification('Keyword deleted', 'success');
                    } else {
                        this.showNotification('Failed to delete keyword', 'error');
                    }
                });
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ============================================
        // Matching Roles Carousel
        // ============================================

        /**
         * Carousel state
         */
        carouselState: {
            currentIndex: 0,
            itemsPerView: 3,
            totalItems: 0,
            isAnimating: false
        },

        /**
         * Initialize matching roles carousel
         */
        initMatchingRolesCarousel: function() {
            const $carousel = $('#sffc-roles-carousel');
            if (!$carousel.length) return;

            const $track = $('#sffc-carousel-track');
            const $cards = $track.find('.sffc-job-match-card');

            if ($cards.length === 0) return;

            this.carouselState.totalItems = $cards.length;
            this.updateItemsPerView();

            // Bind navigation buttons
            $('.sffc-carousel-prev').on('click', () => this.carouselPrev());
            $('.sffc-carousel-next').on('click', () => this.carouselNext());

            // Bind indicator dots
            $('.sffc-carousel-dot').on('click', (e) => {
                const index = $(e.currentTarget).data('index');
                this.carouselGoTo(index);
            });

            // Touch/swipe support
            this.initCarouselTouch($carousel);

            // Keyboard navigation
            $carousel.attr('tabindex', '0').on('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    this.carouselPrev();
                } else if (e.key === 'ArrowRight') {
                    this.carouselNext();
                }
            });

            // Window resize handler
            $(window).on('resize', this.debounce(() => {
                this.updateItemsPerView();
                this.carouselGoTo(0);
            }, 250));

            // Initialize mini donut charts
            this.initMiniDonutCharts();

            // Update button states
            this.updateCarouselButtons();
        },

        /**
         * Update items per view based on screen width
         */
        updateItemsPerView: function() {
            const width = $(window).width();
            if (width < 576) {
                this.carouselState.itemsPerView = 1;
            } else if (width < 992) {
                this.carouselState.itemsPerView = 2;
            } else {
                this.carouselState.itemsPerView = 3;
            }
        },

        /**
         * Navigate to previous slide
         */
        carouselPrev: function() {
            if (this.carouselState.isAnimating) return;
            if (this.carouselState.currentIndex > 0) {
                this.carouselGoTo(this.carouselState.currentIndex - 1);
            }
        },

        /**
         * Navigate to next slide
         */
        carouselNext: function() {
            if (this.carouselState.isAnimating) return;
            const maxIndex = Math.ceil(this.carouselState.totalItems / this.carouselState.itemsPerView) - 1;
            if (this.carouselState.currentIndex < maxIndex) {
                this.carouselGoTo(this.carouselState.currentIndex + 1);
            }
        },

        /**
         * Go to specific carousel page
         */
        carouselGoTo: function(index) {
            if (this.carouselState.isAnimating) return;

            const $track = $('#sffc-carousel-track');
            const $cards = $track.find('.sffc-job-match-card');
            const cardWidth = $cards.first().outerWidth(true);
            const offset = index * this.carouselState.itemsPerView * cardWidth;

            this.carouselState.isAnimating = true;
            this.carouselState.currentIndex = index;

            $track.css('transform', `translateX(-${offset}px)`);

            setTimeout(() => {
                this.carouselState.isAnimating = false;
            }, 300);

            this.updateCarouselButtons();
            this.updateCarouselDots();
        },

        /**
         * Update carousel navigation buttons
         */
        updateCarouselButtons: function() {
            const maxIndex = Math.ceil(this.carouselState.totalItems / this.carouselState.itemsPerView) - 1;

            $('.sffc-carousel-prev').prop('disabled', this.carouselState.currentIndex === 0);
            $('.sffc-carousel-next').prop('disabled', this.carouselState.currentIndex >= maxIndex);
        },

        /**
         * Update carousel indicator dots
         */
        updateCarouselDots: function() {
            $('.sffc-carousel-dot').removeClass('active');
            $(`.sffc-carousel-dot[data-index="${this.carouselState.currentIndex}"]`).addClass('active');
        },

        /**
         * Initialize touch/swipe support for carousel
         */
        initCarouselTouch: function($carousel) {
            let touchStartX = 0;
            let touchEndX = 0;

            $carousel.on('touchstart', (e) => {
                touchStartX = e.originalEvent.touches[0].clientX;
            });

            $carousel.on('touchend', (e) => {
                touchEndX = e.originalEvent.changedTouches[0].clientX;
                const diff = touchStartX - touchEndX;

                if (Math.abs(diff) > 50) { // Minimum swipe distance
                    if (diff > 0) {
                        this.carouselNext();
                    } else {
                        this.carouselPrev();
                    }
                }
            });
        },

        /**
         * Initialize mini donut charts for job cards
         */
        initMiniDonutCharts: function() {
            $('.sffc-mini-donut').each(function() {
                const canvas = this;
                const $canvas = $(canvas);
                const score = parseInt($canvas.data('score'), 10) || 0;

                if (typeof Chart === 'undefined') return;

                // Destroy existing chart if any
                const existingChart = Chart.getChart(canvas);
                if (existingChart) {
                    existingChart.destroy();
                }

                // Determine color based on score
                let color = '#f59e0b'; // Yellow/amber for medium
                if (score >= 80) {
                    color = '#10b981'; // Green for excellent
                } else if (score < 60) {
                    color = '#ef4444'; // Red for fair
                }

                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [score, 100 - score],
                            backgroundColor: [color, '#e5e7eb'],
                            borderWidth: 0,
                            cutout: '75%'
                        }]
                    },
                    options: {
                        responsive: false,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        },
                        animation: {
                            animateRotate: true,
                            duration: 800
                        }
                    }
                });
            });
        },

        /**
         * Debounce utility function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        // ============================================
        // Sidebar Navigation
        // ============================================

        /**
         * Current active section
         */
        activeSection: 'overview',

        /**
         * Sidebar collapsed state
         */
        sidebarCollapsed: false,

        /**
         * Initialize sidebar navigation
         */
        initSidebar: function() {
            const self = this;

            // Get initial active section from data attribute
            const $dashboard = $('.sffc-career-dashboard');
            if ($dashboard.length) {
                this.activeSection = $dashboard.data('active-section') || 'overview';
            }

            // Sidebar navigation clicks
            $('.sffc-nav-item[data-section]').on('click', function() {
                const section = $(this).data('section');
                self.switchSection(section);
            });

            // Mobile navigation clicks
            $('.sffc-mobile-nav-item[data-section]').on('click', function() {
                const section = $(this).data('section');
                self.switchSection(section);
            });

            // Sidebar toggle (collapse/expand)
            $('#sffc-sidebar-toggle').on('click', () => this.toggleSidebar());

            // Help button
            $('#sffc-help-btn').on('click', () => this.restartOnboarding());

            // Mobile menu toggle button
            $('.sffc-mobile-menu-toggle').on('click', () => {
                this.openMobileSidebar();
            });

            // Sidebar overlay click to close
            $('.sffc-sidebar-overlay').on('click', () => {
                this.closeMobileSidebar();
            });

            // Mobile sidebar close button
            $('#sffc-sidebar-close').on('click', () => {
                this.closeMobileSidebar();
            });

            // Close sidebar when clicking a nav item on mobile
            $('.sffc-nav-item[data-section]').on('click', () => {
                if (window.innerWidth <= 992) {
                    this.closeMobileSidebar();
                }
            });

            // Mobile header action buttons
            $('#sffc-mobile-refresh').on('click', () => this.refreshDashboard());
            $('#sffc-mobile-settings').on('click', () => this.openSettings());

            // Handle browser back/forward
            $(window).on('popstate', (e) => {
                if (e.originalEvent.state && e.originalEvent.state.section) {
                    this.switchSection(e.originalEvent.state.section, false);
                }
            });

            // Keyboard navigation
            $(document).on('keydown', (e) => {
                // Alt + number keys for quick section switching
                if (e.altKey && e.key >= '1' && e.key <= '7') {
                    const sections = ['overview', 'jobs', 'trends', 'skills', 'market', 'salary', 'profile'];
                    const index = parseInt(e.key) - 1;
                    if (sections[index]) {
                        e.preventDefault();
                        this.switchSection(sections[index]);
                    }
                }
            });

            // Check for collapsed state from localStorage
            const savedCollapsed = localStorage.getItem('sffc_sidebar_collapsed');
            if (savedCollapsed === 'true') {
                this.sidebarCollapsed = true;
                $('.sffc-career-dashboard').addClass('sffc-sidebar-collapsed');
            }

            // Handle resize for mobile
            $(window).on('resize', this.debounce(() => {
                this.handleResponsiveSidebar();
            }, 250));

            this.handleResponsiveSidebar();
        },

        /**
         * Switch to a different section
         */
        switchSection: function(section, updateHistory = true) {
            if (section === this.activeSection) return;

            const $panels = $('.sffc-section-panel');
            const $navItems = $('.sffc-nav-item[data-section]');
            const $mobileNavItems = $('.sffc-mobile-nav-item[data-section]');

            // Update active section
            this.activeSection = section;

            // Animate out current panel
            $panels.filter('.active').removeClass('active').addClass('exiting');

            // Small delay for exit animation
            setTimeout(() => {
                $panels.removeClass('exiting');

                // Show new panel
                $panels.filter(`[data-section="${section}"]`).addClass('active');

                // Update sidebar nav
                $navItems.removeClass('active').attr('aria-current', 'false');
                $navItems.filter(`[data-section="${section}"]`).addClass('active').attr('aria-current', 'page');

                // Update mobile nav
                $mobileNavItems.removeClass('active');
                $mobileNavItems.filter(`[data-section="${section}"]`).addClass('active');

                // Update URL without reload
                if (updateHistory) {
                    const url = new URL(window.location);
                    url.searchParams.set('section', section);
                    history.pushState({ section: section }, '', url);
                }

                // Scroll to top of content
                $('.sffc-dashboard-main').animate({ scrollTop: 0 }, 200);

                // Re-initialize charts if needed
                this.initSectionCharts(section);

                // Save preference
                this.saveActiveSection(section);

            }, 150);
        },

        /**
         * Initialize charts for a section (lazy loading)
         */
        initSectionCharts: function(section) {
            switch (section) {
                case 'overview':
                    this.initMiniDonutCharts();
                    break;
                case 'jobs':
                    this.initMiniDonutCharts();
                    break;
                case 'trends':
                    // Re-render trends chart with cached data, or load if not available
                    if (this.data.trends) {
                        this.renderTrendsChart(this.data.trends);
                    } else {
                        this.loadTrends();
                    }
                    break;
                case 'skills':
                    // Re-render skills charts with cached data, or load if not available
                    if (this.data.skills) {
                        this.renderSkillsChart(this.data.skills);
                    } else {
                        this.loadSkillsAnalysis();
                    }
                    break;
                case 'salary':
                    // Re-render salary charts if needed
                    if (typeof this.renderSalaryCharts === 'function') {
                        this.renderSalaryCharts();
                    }
                    break;
            }
        },

        /**
         * Toggle sidebar collapsed state
         */
        toggleSidebar: function() {
            this.sidebarCollapsed = !this.sidebarCollapsed;

            $('.sffc-career-dashboard').toggleClass('sffc-sidebar-collapsed', this.sidebarCollapsed);

            // Save preference
            localStorage.setItem('sffc_sidebar_collapsed', this.sidebarCollapsed);

            // Update toggle button icon
            const $toggle = $('#sffc-sidebar-toggle');
            if (this.sidebarCollapsed) {
                $toggle.attr('aria-label', 'Expand sidebar');
            } else {
                $toggle.attr('aria-label', 'Collapse sidebar');
            }
        },

        /**
         * Open mobile sidebar
         */
        openMobileSidebar: function() {
            $('.sffc-dashboard-sidebar').addClass('sffc-sidebar-open');
            $('.sffc-sidebar-overlay').addClass('show');
            $('body').addClass('sffc-sidebar-open-body');
        },

        /**
         * Close mobile sidebar
         */
        closeMobileSidebar: function() {
            $('.sffc-dashboard-sidebar').removeClass('sffc-sidebar-open');
            $('.sffc-sidebar-overlay').removeClass('show');
            $('body').removeClass('sffc-sidebar-open-body');
        },

        /**
         * Initialize touch gestures for mobile sidebar
         */
        initTouchGestures: function() {
            const sidebar = document.getElementById('sffc-sidebar');
            const overlay = document.querySelector('.sffc-sidebar-overlay');
            if (!sidebar) return;

            let touchStartX = 0;
            let touchEndX = 0;
            let touchStartY = 0;
            const swipeThreshold = 80;

            // Swipe left to close sidebar
            sidebar.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            sidebar.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                const touchEndY = e.changedTouches[0].screenY;

                // Only handle horizontal swipes (not scrolling)
                const deltaX = touchStartX - touchEndX;
                const deltaY = Math.abs(touchStartY - touchEndY);

                if (deltaX > swipeThreshold && deltaY < 100) {
                    // Swiped left - close sidebar
                    this.closeMobileSidebar();
                }
            }, { passive: true });

            // Swipe right from edge to open sidebar
            document.addEventListener('touchstart', (e) => {
                // Only track if touch starts near the left edge
                if (e.changedTouches[0].screenX < 30 && window.innerWidth <= 992) {
                    touchStartX = e.changedTouches[0].screenX;
                    touchStartY = e.changedTouches[0].screenY;
                }
            }, { passive: true });

            document.addEventListener('touchend', (e) => {
                if (touchStartX < 30 && window.innerWidth <= 992) {
                    touchEndX = e.changedTouches[0].screenX;
                    const touchEndY = e.changedTouches[0].screenY;

                    const deltaX = touchEndX - touchStartX;
                    const deltaY = Math.abs(touchStartY - touchEndY);

                    if (deltaX > swipeThreshold && deltaY < 100) {
                        // Swiped right from edge - open sidebar
                        this.openMobileSidebar();
                    }
                }
                touchStartX = 0;
            }, { passive: true });
        },

        /**
         * Handle responsive sidebar behavior
         */
        handleResponsiveSidebar: function() {
            const width = $(window).width();

            if (width < 992) {
                // On mobile/tablet, always collapse sidebar
                $('.sffc-career-dashboard').addClass('sffc-sidebar-collapsed');
                $('.sffc-mobile-nav').show();
            } else {
                // On desktop, restore saved state
                if (!this.sidebarCollapsed) {
                    $('.sffc-career-dashboard').removeClass('sffc-sidebar-collapsed');
                }
                $('.sffc-mobile-nav').hide();
            }
        },

        /**
         * Save active section preference
         */
        saveActiveSection: function(section) {
            // Save to localStorage for quick restore
            localStorage.setItem('sffc_active_section', section);

            // Optionally save to server
            this.ajaxRequest('sffc_dashboard_save_preference', {
                preference: 'active_section',
                value: section
            }).catch(() => {
                // Silent fail - not critical
            });
        },

        /**
         * Navigate to section programmatically
         */
        goToSection: function(section) {
            this.switchSection(section);
        },

        /**
         * Initialize application stage dropdown functionality
         */
        initStageDropdowns: function() {
            const self = this;

            // Toggle dropdown on trigger click
            $(document).on('click', '.sffc-stage-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $dropdown = $(this).closest('.sffc-stage-dropdown');

                // Close all other dropdowns
                $('.sffc-stage-dropdown').not($dropdown).removeClass('open');

                // Toggle this dropdown
                $dropdown.toggleClass('open');
            });

            // Select stage option
            $(document).on('click', '.sffc-stage-option', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $option = $(this);
                const stage = $option.data('stage');
                const $dropdown = $option.closest('.sffc-stage-dropdown');
                const $trigger = $dropdown.find('.sffc-stage-trigger');
                const jobId = $trigger.data('job-id');

                // Update the stage
                self.updateJobStage(jobId, stage, $trigger, $dropdown);

                // Close dropdown
                $dropdown.removeClass('open');
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.sffc-stage-dropdown').length) {
                    $('.sffc-stage-dropdown').removeClass('open');
                }
            });

            // Close dropdown on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.sffc-stage-dropdown').removeClass('open');
                }
            });
        },

        /**
         * Update job application stage
         */
        updateJobStage: function(jobId, stage, $trigger, $dropdown) {
            const self = this;
            const previousStage = $trigger.data('current-stage') || '';

            // Stage labels mapping
            const stageLabels = {
                'applied': 'Applied',
                'waiting': 'Waiting',
                'first-interview': '1st Interview',
                'further-interview': 'Further Interview',
                'secured': 'Secured',
                'moved-on': 'Moved On'
            };

            // Update trigger UI immediately
            if (stage && stage !== '') {
                $trigger.addClass('has-stage');
                $trigger.data('current-stage', stage);
                $trigger.html(`
                    <span class="sffc-stage-indicator" data-stage="${stage}"></span>
                    <span class="sffc-stage-text">${stageLabels[stage] || 'Track'}</span>
                    <svg class="sffc-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                `);

                // Add remove option if not already present
                const $menu = $dropdown.find('.sffc-stage-menu');
                if (!$menu.find('.sffc-stage-remove').length) {
                    $menu.append(`
                        <div class="sffc-stage-divider"></div>
                        <button type="button" class="sffc-stage-option sffc-stage-remove" data-stage="">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                            Remove Tracking
                        </button>
                    `);
                }
            } else {
                // Remove tracking
                $trigger.removeClass('has-stage');
                $trigger.data('current-stage', '');
                $trigger.html(`
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    <span class="sffc-stage-text">Track</span>
                    <svg class="sffc-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                `);

                // Remove the "Remove Tracking" option
                $dropdown.find('.sffc-stage-divider, .sffc-stage-remove').remove();
            }

            // Mark selected option in menu
            $dropdown.find('.sffc-stage-option').removeClass('selected');
            if (stage) {
                $dropdown.find(`.sffc-stage-option[data-stage="${stage}"]`).addClass('selected');
            }

            // Update feedback card analytics
            self.updateFeedbackCard(previousStage, stage);

            // Save to backend
            self.saveJobStage(jobId, stage, previousStage);
        },

        /**
         * Update feedback card analytics when stage changes
         */
        updateFeedbackCard: function(previousStage, newStage) {
            const $feedbackCard = $('.sffc-feedback-card');
            if (!$feedbackCard.length) return;

            // Stage to feedback card mapping
            const stageMapping = {
                'waiting': 'waiting',
                'applied': 'waiting', // Applied also counts as waiting
                'moved-on': 'moved',
                'first-interview': 'first',
                'further-interview': 'further',
                'secured': 'secured'
            };

            // Decrement previous stage count
            if (previousStage && stageMapping[previousStage]) {
                const prevFeedbackStage = stageMapping[previousStage];
                const $prevCount = $feedbackCard.find(`.sffc-pipeline-stage[data-stage="${prevFeedbackStage}"] .sffc-stage-count`);
                if ($prevCount.length) {
                    const currentCount = parseInt($prevCount.text()) || 0;
                    const newCount = Math.max(0, currentCount - 1);
                    $prevCount.text(newCount);
                    this.updateStageProgressBar(prevFeedbackStage, newCount);
                }
            }

            // Increment new stage count
            if (newStage && stageMapping[newStage]) {
                const newFeedbackStage = stageMapping[newStage];
                const $newCount = $feedbackCard.find(`.sffc-pipeline-stage[data-stage="${newFeedbackStage}"] .sffc-stage-count`);
                if ($newCount.length) {
                    const currentCount = parseInt($newCount.text()) || 0;
                    const newCountVal = currentCount + 1;
                    $newCount.text(newCountVal);
                    this.updateStageProgressBar(newFeedbackStage, newCountVal);
                }
            }
        },

        /**
         * Update progress bar for a feedback stage
         */
        updateStageProgressBar: function(stage, count) {
            const $feedbackCard = $('.sffc-feedback-card');
            const $stage = $feedbackCard.find(`.sffc-pipeline-stage[data-stage="${stage}"]`);
            const $bar = $stage.find('.sffc-stage-fill');

            if (!$bar.length) return;

            // Calculate total applications for percentage
            let total = 0;
            $feedbackCard.find('.sffc-stage-count').each(function() {
                total += parseInt($(this).text()) || 0;
            });

            // Update bar width
            const percentage = total > 0 ? Math.round((count / total) * 100) : 0;
            $bar.css('width', percentage + '%');
        },

        /**
         * Save job stage to backend
         */
        saveJobStage: function(jobId, stage, previousStage) {
            const self = this;

            this.ajaxRequest('sffc_save_job_stage', {
                job_id: jobId,
                stage: stage,
                previous_stage: previousStage
            }).then(function(response) {
                if (response.success) {
                    // Stage saved successfully
                    self.showNotification('Application stage updated', 'success');

                    // Update overview stats if available
                    if (response.data && response.data.stats) {
                        self.updateOverviewStats(response.data.stats);
                    }
                } else {
                    // Show error but don't revert UI (better UX)
                    console.error('Failed to save stage:', response.data);
                    self.showNotification('Failed to save stage', 'error');
                }
            }).catch(function(error) {
                console.error('Error saving stage:', error);
                self.showNotification('Connection error', 'error');
            });
        },

        /**
         * Update overview stats from server response
         */
        updateOverviewStats: function(stats) {
            // Update total applications count
            if (typeof stats.total !== 'undefined') {
                $('[data-value="total-applications"]').text(stats.total);
            }

            // Update pipeline counts in feedback card
            const stageMap = {
                'applied': 'applied',
                'waiting': 'waiting',
                'first-interview': 'first-interview',
                'further-interview': 'further-interview',
                'secured': 'secured',
                'moved-on': 'moved-on'
            };

            for (const [key, value] of Object.entries(stats)) {
                if (stageMap[key]) {
                    $(`[data-count="${key}"]`).text(value);
                }
            }

            // Recalculate progress bars
            const total = stats.total || 0;
            if (total > 0) {
                for (const [key, value] of Object.entries(stats)) {
                    if (stageMap[key]) {
                        const pct = Math.round((value / total) * 100);
                        $(`.sffc-pipeline-stage[data-stage="${stageMap[key]}"] .sffc-stage-fill`).css('width', pct + '%');
                    }
                }
            }
        },

        /**
         * Load more jobs in the jobs grid
         */
        loadMoreJobs: function() {
            const self = this;
            const $btn = $('#sffc-load-more-jobs');
            const $grid = $('#sffc-jobs-grid');

            // Get current page from button data
            let currentPage = parseInt($btn.data('page')) || 1;
            const nextPage = currentPage + 1;

            // Show loading state
            $btn.prop('disabled', true).html('<svg class="sffc-spinner" width="16" height="16" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" stroke-dasharray="40" stroke-dashoffset="10"/></svg> Loading...');

            this.ajaxRequest('sffc_load_more_jobs', {
                page: nextPage,
                per_page: 12
            }).then(function(response) {
                if (response.success && response.data.jobs) {
                    // Append new jobs to grid
                    response.data.jobs.forEach(function(jobHtml) {
                        const $job = $(jobHtml);
                        $job.css('opacity', '0');
                        $grid.append($job);

                        // Animate in
                        setTimeout(function() {
                            $job.css({
                                'opacity': '1',
                                'transition': 'opacity 0.3s ease'
                            });
                        }, 50);
                    });

                    // Update page counter
                    $btn.data('page', nextPage);

                    // Hide button if no more jobs
                    if (!response.data.has_more) {
                        $btn.closest('.sffc-jobs-load-more').fadeOut(300);
                    } else {
                        $btn.prop('disabled', false).html('Load More Jobs');
                    }

                    // Re-initialize stage dropdowns for new cards
                    self.initStageDropdowns();
                } else {
                    self.showNotification('No more jobs to load', 'info');
                    $btn.closest('.sffc-jobs-load-more').fadeOut(300);
                }
            }).catch(function() {
                self.showNotification('Failed to load more jobs', 'error');
                $btn.prop('disabled', false).html('Load More Jobs');
            });
        },

        /**
         * Show a temporary notification
         */
        showNotification: function(message, type) {
            type = type || 'info';

            // Remove existing notification
            $('.sffc-notification').remove();

            // Create notification
            const $notification = $(`
                <div class="sffc-notification sffc-notification-${type}">
                    <span class="sffc-notification-message">${message}</span>
                </div>
            `);

            // Add to page
            $('body').append($notification);

            // Animate in
            setTimeout(function() {
                $notification.addClass('show');
            }, 10);

            // Auto-hide after 3 seconds
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Initialize apply button tracking
         * Tracks when users click on apply buttons and records the application
         */
        initApplyTracking: function() {
            const self = this;

            // Track clicks on apply buttons
            $(document).on('click', '.sffc-track-apply, .sffc-jb-apply-btn', function() {
                const $btn = $(this);
                const jobData = {
                    job_id: $btn.data('job-id'),
                    job_title: $btn.data('job-title'),
                    company: $btn.data('company'),
                    location: $btn.data('location')
                };

                // Don't track if no job_id
                if (!jobData.job_id) {
                    return;
                }

                // Track the application via AJAX (fire and forget - don't block the click)
                self.ajaxRequest('sffc_track_job_apply', jobData).then(function(response) {
                    if (response.success && response.data && response.data.tracked) {
                        // Update total applications count in the UI
                        const $totalApps = $('[data-value="total-applications"]');
                        if ($totalApps.length) {
                            const currentCount = parseInt($totalApps.text()) || 0;
                            $totalApps.text(currentCount + 1);
                        }
                    }
                }).catch(function(error) {
                    // Silently fail - don't interrupt user's apply action
                    console.warn('Failed to track application:', error);
                });
            });
        },

        /**
         * Initialize sparkline charts in KPI cards
         */
        initSparklines: function() {
            const self = this;
            $('.sffc-kpi-sparkline').each(function() {
                const $container = $(this);
                const $svg = $container.find('.sffc-sparkline-svg');
                const dataValues = $container.attr('data-values');

                if (!dataValues || !$svg.length) {
                    return;
                }

                try {
                    const values = JSON.parse(dataValues);
                    if (!Array.isArray(values) || values.length < 2) {
                        return;
                    }

                    self.renderSparkline($svg[0], values);
                } catch (e) {
                    console.error('Sparkline parse error:', e);
                }
            });
        },

        /**
         * Render a sparkline SVG path
         */
        renderSparkline: function(svgElement, values) {
            if (!values || values.length < 2) {
                return;
            }

            const width = 100;
            const height = 24;
            const padding = 2;

            // Normalize values
            const min = Math.min(...values);
            const max = Math.max(...values);
            const range = max - min || 1;

            // Calculate points
            const stepX = (width - padding * 2) / (values.length - 1);
            const points = values.map((val, i) => {
                const x = padding + i * stepX;
                const y = height - padding - ((val - min) / range) * (height - padding * 2);
                return { x, y };
            });

            // Generate smooth curve path using Catmull-Rom to Bezier conversion
            let path = `M ${points[0].x} ${points[0].y}`;

            for (let i = 0; i < points.length - 1; i++) {
                const p0 = points[Math.max(0, i - 1)];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[Math.min(points.length - 1, i + 2)];

                // Tension factor for smoothness
                const tension = 0.3;

                const cp1x = p1.x + (p2.x - p0.x) * tension;
                const cp1y = p1.y + (p2.y - p0.y) * tension;
                const cp2x = p2.x - (p3.x - p1.x) * tension;
                const cp2y = p2.y - (p3.y - p1.y) * tension;

                path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
            }

            // Create line path
            const linePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            linePath.setAttribute('d', path);
            linePath.setAttribute('class', 'sffc-sparkline-line');
            linePath.setAttribute('fill', 'none');
            linePath.setAttribute('stroke', 'currentColor');
            linePath.setAttribute('stroke-width', '2');
            linePath.setAttribute('stroke-linecap', 'round');
            linePath.setAttribute('stroke-linejoin', 'round');

            // Create gradient fill path
            const fillPath = path + ` L ${points[points.length - 1].x} ${height} L ${points[0].x} ${height} Z`;
            const areaPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            areaPath.setAttribute('d', fillPath);
            areaPath.setAttribute('class', 'sffc-sparkline-area');
            areaPath.setAttribute('fill', 'url(#sparkline-gradient)');
            areaPath.setAttribute('opacity', '0.2');

            // Create gradient definition if not exists
            let defs = svgElement.querySelector('defs');
            if (!defs) {
                defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
                const gradient = document.createElementNS('http://www.w3.org/2000/svg', 'linearGradient');
                gradient.setAttribute('id', 'sparkline-gradient');
                gradient.setAttribute('x1', '0%');
                gradient.setAttribute('y1', '0%');
                gradient.setAttribute('x2', '0%');
                gradient.setAttribute('y2', '100%');

                const stop1 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
                stop1.setAttribute('offset', '0%');
                stop1.setAttribute('stop-color', 'currentColor');

                const stop2 = document.createElementNS('http://www.w3.org/2000/svg', 'stop');
                stop2.setAttribute('offset', '100%');
                stop2.setAttribute('stop-color', 'currentColor');
                stop2.setAttribute('stop-opacity', '0');

                gradient.appendChild(stop1);
                gradient.appendChild(stop2);
                defs.appendChild(gradient);
                svgElement.insertBefore(defs, svgElement.firstChild);
            }

            // Clear existing paths and add new ones
            $(svgElement).find('path').remove();
            svgElement.appendChild(areaPath);
            svgElement.appendChild(linePath);

            // Add end dot
            const lastPoint = points[points.length - 1];
            const endDot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            endDot.setAttribute('cx', lastPoint.x);
            endDot.setAttribute('cy', lastPoint.y);
            endDot.setAttribute('r', '3');
            endDot.setAttribute('fill', 'currentColor');
            endDot.setAttribute('class', 'sffc-sparkline-dot');
            svgElement.appendChild(endDot);
        },

        /**
         * Initialize company logo error handling
         */
        initLogoFallbacks: function() {
            // Handle broken logo images by showing placeholder
            $(document).on('error', '.sffc-job-row-logo img, .sffc-job-company-logo img', function() {
                const $img = $(this);
                const $container = $img.parent();
                const company = $container.closest('[data-company]').data('company') ||
                               $container.siblings('.sffc-job-row-main').find('.sffc-job-row-company').text() ||
                               'C';

                // Get first letter for placeholder
                const initial = company.charAt(0).toUpperCase();

                // Replace image with placeholder
                $img.hide();
                if (!$container.find('.sffc-logo-placeholder').length) {
                    $container.append('<div class="sffc-logo-placeholder">' + initial + '</div>');
                }
            });

            // Add loading class to images
            $('.sffc-job-row-logo img, .sffc-job-company-logo img').each(function() {
                const $img = $(this);
                if (!$img[0].complete) {
                    $img.addClass('loading');
                    $img.on('load', function() {
                        $(this).removeClass('loading').addClass('loaded');
                    });
                }
            });
        },

        /**
         * Initialize relative time updates for trust indicators
         */
        initRelativeTime: function() {
            const self = this;

            function updateRelativeTimes() {
                $('.sffc-relative-time').each(function() {
                    const $time = $(this);
                    const datetime = $time.attr('datetime');
                    if (!datetime) return;

                    const then = new Date(datetime);
                    const now = new Date();
                    const diffMs = now - then;
                    const diffMins = Math.floor(diffMs / 60000);

                    let text;
                    if (diffMins < 1) {
                        text = 'just now';
                    } else if (diffMins < 60) {
                        text = diffMins + ' min ago';
                    } else if (diffMins < 1440) {
                        const hours = Math.floor(diffMins / 60);
                        text = hours + (hours === 1 ? ' hour ago' : ' hours ago');
                    } else {
                        const days = Math.floor(diffMins / 1440);
                        text = days + (days === 1 ? ' day ago' : ' days ago');
                    }

                    $time.text(text);
                });
            }

            updateRelativeTimes();
            setInterval(updateRelativeTimes, 60000);
        },

        /**
         * Update data freshness timestamp after refresh
         */
        updateDataFreshness: function() {
            const now = new Date();
            const $freshness = $('.sffc-relative-time');
            if ($freshness.length) {
                $freshness.attr('datetime', now.toISOString());
                $freshness.text('just now');
            }
        },

        /**
         * Initialize contact management for networking and recruiters
         */
        initContactManagement: function() {
            const self = this;

            // Open add contact modal
            $(document).on('click', '#sffc-add-contact-btn, #sffc-add-first-contact', function(e) {
                e.preventDefault();
                $('#sffc-add-contact-modal').addClass('active');
            });

            // Open add recruiter modal
            $(document).on('click', '#sffc-add-recruiter-btn, #sffc-add-first-recruiter', function(e) {
                e.preventDefault();
                $('#sffc-add-recruiter-modal').addClass('active');
            });

            // Close modals
            $(document).on('click', '.sffc-modal-overlay, .sffc-modal-close, .sffc-modal-cancel', function() {
                $(this).closest('.sffc-modal').removeClass('active');
            });

            // Close modal on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.sffc-modal.active').removeClass('active');
                }
            });

            // Handle contact form submission
            $(document).on('submit', '.sffc-contact-form', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $modal = $form.closest('.sffc-modal');
                const $submitBtn = $form.find('button[type="submit"]');
                const originalText = $submitBtn.text();
                const contactId = $form.data('contact-id') || 0;
                const isEdit = contactId > 0;

                // Collect form data
                const formData = {
                    action: isEdit ? 'sffc_update_contact' : 'sffc_add_contact',
                    nonce: sffc_dashboard?.nonce || '',
                    contact_id: contactId,
                    interaction_type: $form.find('[name="interaction_type"]').val(),
                    contact_name: $form.find('[name="contact_name"]').val(),
                    company: $form.find('[name="company"]').val(),
                    contact_email: $form.find('[name="contact_email"]').val(),
                    status: $form.find('[name="status"]').val(),
                    follow_up_date: $form.find('[name="follow_up_date"]').val(),
                    notes: $form.find('[name="notes"]').val()
                };

                // Validate required fields
                if (!formData.contact_name) {
                    self.showNotification('Please enter a contact name', 'error');
                    return;
                }

                // Show loading state
                $submitBtn.prop('disabled', true).text('Saving...');

                // Submit via AJAX
                $.post(sffc_dashboard?.ajax_url || '/wp-admin/admin-ajax.php', formData)
                    .done(function(response) {
                        if (response.success) {
                            self.showNotification(isEdit ? 'Contact updated' : 'Contact added', 'success');
                            $modal.removeClass('active');
                            $form[0].reset();
                            $form.removeData('contact-id');

                            // Reload the section to show updated data
                            const section = formData.interaction_type === 'recruiter' ? 'recruiters' : 'networking';
                            self.reloadSection(section);
                        } else {
                            self.showNotification(response.data?.message || 'Failed to save contact', 'error');
                        }
                    })
                    .fail(function() {
                        self.showNotification('Network error. Please try again.', 'error');
                    })
                    .always(function() {
                        $submitBtn.prop('disabled', false).text(originalText);
                    });
            });

            // Edit contact
            $(document).on('click', '.sffc-edit-contact', function(e) {
                e.preventDefault();
                const $card = $(this).closest('.sffc-contact-card');
                const contactId = $card.data('contact-id');
                const contactType = $card.data('type');
                const modalId = contactType === 'recruiter' ? '#sffc-add-recruiter-modal' : '#sffc-add-contact-modal';
                const $modal = $(modalId);
                const $form = $modal.find('.sffc-contact-form');

                // Fetch contact details via AJAX
                $.post(sffc_dashboard?.ajax_url || '/wp-admin/admin-ajax.php', {
                    action: 'sffc_get_contact',
                    nonce: sffc_dashboard?.nonce || '',
                    contact_id: contactId
                })
                .done(function(response) {
                    if (response.success && response.data) {
                        const contact = response.data;

                        // Populate form fields
                        $form.data('contact-id', contactId);
                        $form.find('[name="contact_name"]').val(contact.contact_name || '');
                        $form.find('[name="company"]').val(contact.company || '');
                        $form.find('[name="contact_email"]').val(contact.contact_email || '');
                        $form.find('[name="status"]').val(contact.status || 'pending');
                        $form.find('[name="follow_up_date"]').val(contact.follow_up_date || '');
                        $form.find('[name="notes"]').val(contact.notes || '');

                        // Update modal title
                        $modal.find('.sffc-modal-header h3').text('Edit Contact');
                        $modal.find('button[type="submit"]').text('Update Contact');

                        // Show modal
                        $modal.addClass('active');
                    }
                });
            });

            // Delete contact
            $(document).on('click', '.sffc-delete-contact', function(e) {
                e.preventDefault();
                const $card = $(this).closest('.sffc-contact-card');
                const contactId = $card.data('contact-id');
                const contactType = $card.data('type');

                if (!confirm('Are you sure you want to delete this contact?')) {
                    return;
                }

                $.post(sffc_dashboard?.ajax_url || '/wp-admin/admin-ajax.php', {
                    action: 'sffc_delete_contact',
                    nonce: sffc_dashboard?.nonce || '',
                    contact_id: contactId
                })
                .done(function(response) {
                    if (response.success) {
                        // Animate removal
                        $card.fadeOut(300, function() {
                            $(this).remove();

                            // Check if list is empty
                            const $list = $('.sffc-contacts-list');
                            if ($list.children().length === 0) {
                                // Reload section to show empty state
                                const section = contactType === 'recruiter' ? 'recruiters' : 'networking';
                                self.reloadSection(section);
                            }
                        });

                        self.showNotification('Contact deleted', 'success');
                    } else {
                        self.showNotification(response.data?.message || 'Failed to delete contact', 'error');
                    }
                })
                .fail(function() {
                    self.showNotification('Network error. Please try again.', 'error');
                });
            });

            // Reset modal when closed
            $(document).on('click', '.sffc-modal-overlay, .sffc-modal-close, .sffc-modal-cancel', function() {
                const $modal = $(this).closest('.sffc-modal');
                if (!$modal.length) return;

                const $form = $modal.find('.sffc-contact-form, form');
                const isRecruiter = $modal.attr('id') === 'sffc-add-recruiter-modal';

                // Reset form and title
                setTimeout(function() {
                    if ($form.length && $form[0]) {
                        $form[0].reset();
                        $form.removeData('contact-id');
                    }
                    $modal.find('.sffc-modal-header h3').text(isRecruiter ? 'Add Recruiter' : 'Add Contact');
                    $modal.find('button[type="submit"]').text('Save Contact');
                }, 300);
            });
        },

        /**
         * Reload a dashboard section via AJAX
         */
        reloadSection: function(sectionName) {
            const $section = $('[data-section="' + sectionName + '"] .sffc-dashboard-section');
            if (!$section.length) return;

            // Show loading state
            $section.css('opacity', '0.5');

            $.post(sffc_dashboard?.ajax_url || '/wp-admin/admin-ajax.php', {
                action: 'sffc_reload_section',
                nonce: sffc_dashboard?.nonce || '',
                section: sectionName
            })
            .done(function(response) {
                if (response.success && response.data?.html) {
                    $section.html(response.data.html);
                }
            })
            .always(function() {
                $section.css('opacity', '1');
            });
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        Dashboard.init();
        Dashboard.initSidebar();
        Dashboard.initSettings();
        Dashboard.initMobile();
        Dashboard.initTouchGestures();
        Dashboard.initLazyLoading();
        Dashboard.preloadResources();
        Dashboard.initQuickEdit();
        Dashboard.initMissingFields();
        Dashboard.initOnboarding();
        Dashboard.initNewsSources();
        Dashboard.initAlertKeywords();
        Dashboard.initMatchingRolesCarousel();
        Dashboard.initStageDropdowns();
        Dashboard.initApplyTracking();
        Dashboard.initSparklines();
        Dashboard.initRelativeTime();
        Dashboard.initContactManagement();
        Dashboard.initLogoFallbacks();
    });

})(jQuery);
