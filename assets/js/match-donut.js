/**
 * Match Donut Chart Component
 * Renders mini donut charts showing job match scores on job cards
 */

(function($) {
    'use strict';

    // Constants
    const DONUT_RADIUS = 18;
    const DONUT_CIRCUMFERENCE = 2 * Math.PI * DONUT_RADIUS;

    // Weight configuration (must match PHP SFFC_Job_Matcher)
    const WEIGHTS = {
        skills: 0.40,
        experience: 0.30,
        industry: 0.20,
        location: 0.10
    };

    // Match threshold (will be set from user profile)
    let userMatchThreshold = 60;

    /**
     * MatchDonutRenderer Class
     * Handles rendering and updating of donut charts
     */
    class MatchDonutRenderer {
        constructor() {
            this.cache = new Map();
            this.isLoggedIn = window.sffc_profile?.is_logged_in || window.sffc_ajax?.user_id > 0;
            this.hasLocalProfile = this.checkLocalProfile();
            this.init();
        }

        init() {
            // Load user's match threshold
            this.loadUserThreshold();

            // Initialize charts when DOM is ready
            $(document).ready(() => {
                this.initializeCharts();
            });

            // Re-initialize on AJAX load (for load more, filters)
            $(document).on('sffc:jobs:loaded', () => {
                this.initializeCharts();
            });

            // Update when profile is saved
            $(document).on('sffc:profile:updated profileBuilder:saved', () => {
                this.hasLocalProfile = this.checkLocalProfile();
                this.clearCache();
                this.initializeCharts();
            });
        }

        checkLocalProfile() {
            try {
                const profile = localStorage.getItem('sffc_user_profile');
                if (profile) {
                    const data = JSON.parse(profile);
                    // Check minimum fields
                    return !!(data.full_name && data.skills && data.skills.length > 0);
                }
            } catch (e) {
                console.error('Error checking local profile:', e);
            }
            return false;
        }

        loadUserThreshold() {
            // Try localStorage first
            try {
                const profile = localStorage.getItem('sffc_user_profile');
                if (profile) {
                    const data = JSON.parse(profile);
                    if (data.match_threshold) {
                        userMatchThreshold = parseInt(data.match_threshold);
                    }
                }
            } catch (e) {
                console.error('Error loading threshold:', e);
            }

            // Override with server value if available
            if (window.sffcMatchData?.threshold) {
                userMatchThreshold = window.sffcMatchData.threshold;
            }
        }

        initializeCharts() {
            const $cards = $('.sffc-jb-job-card');

            if ($cards.length === 0) return;

            // Collect job IDs that need match calculation
            const jobIds = [];
            $cards.each((index, card) => {
                const $card = $(card);
                const jobId = $card.data('job-id');

                // Skip if already has a chart
                if ($card.find('.sffc-match-donut').length > 0) return;

                jobIds.push(jobId);

                // Insert placeholder
                this.insertPlaceholder($card, jobId);
            });

            // Fetch matches if user has profile
            if (this.isLoggedIn || this.hasLocalProfile) {
                this.fetchMatches(jobIds);
            }
        }

        insertPlaceholder($card, jobId) {
            const $companyLogo = $card.find('.sffc-jb-company-logo');

            // Determine initial state
            let stateClass = 'state-locked';
            if (this.isLoggedIn) {
                stateClass = this.hasLocalProfile ? 'loading' : 'state-incomplete';
            } else if (this.hasLocalProfile) {
                stateClass = 'state-preview loading';
            }

            const html = this.renderDonutHTML({
                score: 0,
                breakdown: { skills: 0, experience: 0, industry: 0, location: 0 },
                state: stateClass,
                jobId: jobId
            });

            // Insert after company logo
            $companyLogo.after(`<div class="sffc-jb-match-container">${html}</div>`);
        }

        renderDonutHTML(data) {
            const { score, breakdown, state, jobId } = data;
            const stateClass = state || this.getStateClass(score);

            // Calculate segment dasharray values
            const segments = this.calculateSegments(breakdown);

            return `
                <div class="sffc-match-donut ${stateClass}"
                     data-job-id="${jobId}"
                     data-score="${score}"
                     role="img"
                     aria-label="Match score: ${score}%"
                     tabindex="0">
                    <svg viewBox="0 0 40 40">
                        <circle class="donut-bg" cx="20" cy="20" r="${DONUT_RADIUS}"/>
                        <circle class="donut-segment segment-location" cx="20" cy="20" r="${DONUT_RADIUS}"
                                stroke-dasharray="${segments.location.dash} ${segments.location.gap}"
                                stroke-dashoffset="${segments.location.offset}"/>
                        <circle class="donut-segment segment-industry" cx="20" cy="20" r="${DONUT_RADIUS}"
                                stroke-dasharray="${segments.industry.dash} ${segments.industry.gap}"
                                stroke-dashoffset="${segments.industry.offset}"/>
                        <circle class="donut-segment segment-experience" cx="20" cy="20" r="${DONUT_RADIUS}"
                                stroke-dasharray="${segments.experience.dash} ${segments.experience.gap}"
                                stroke-dashoffset="${segments.experience.offset}"/>
                        <circle class="donut-segment segment-skills" cx="20" cy="20" r="${DONUT_RADIUS}"
                                stroke-dasharray="${segments.skills.dash} ${segments.skills.gap}"
                                stroke-dashoffset="${segments.skills.offset}"/>
                    </svg>
                    <div class="donut-center">
                        <span class="donut-score">${score}%</span>
                    </div>
                    <div class="donut-lock">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </div>
                    <div class="donut-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <div class="donut-cta">Complete<br>Profile</div>
                    ${this.renderTooltip(breakdown, score)}
                </div>
            `;
        }

        calculateSegments(breakdown) {
            // Each segment represents a weighted portion of the full circle
            // The segment shows the actual score within its allocated weight
            const segments = {};
            let cumulativeOffset = 0;

            // Order: skills, experience, industry, location (clockwise from top)
            const order = ['skills', 'experience', 'industry', 'location'];

            order.forEach(category => {
                const weight = WEIGHTS[category];
                const categoryScore = breakdown[category] || 0;

                // The dash length is the percentage of the weight that's filled
                const maxDash = weight * DONUT_CIRCUMFERENCE;
                const actualDash = (categoryScore / 100) * maxDash;

                segments[category] = {
                    dash: actualDash.toFixed(2),
                    gap: (DONUT_CIRCUMFERENCE - actualDash).toFixed(2),
                    offset: (-cumulativeOffset).toFixed(2)
                };

                cumulativeOffset += maxDash;
            });

            return segments;
        }

        renderTooltip(breakdown, score) {
            return `
                <div class="sffc-match-tooltip">
                    <div class="tooltip-row">
                        <span class="tooltip-label">
                            <span class="tooltip-dot skills"></span>
                            Skills
                        </span>
                        <span class="tooltip-value">${breakdown.skills || 0}%</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">
                            <span class="tooltip-dot experience"></span>
                            Experience
                        </span>
                        <span class="tooltip-value">${breakdown.experience || 0}%</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">
                            <span class="tooltip-dot industry"></span>
                            Industry
                        </span>
                        <span class="tooltip-value">${breakdown.industry || 0}%</span>
                    </div>
                    <div class="tooltip-row">
                        <span class="tooltip-label">
                            <span class="tooltip-dot location"></span>
                            Location
                        </span>
                        <span class="tooltip-value">${breakdown.location || 0}%</span>
                    </div>
                </div>
            `;
        }

        getStateClass(score) {
            if (!this.isLoggedIn && !this.hasLocalProfile) {
                return 'state-locked';
            }
            if (!this.isLoggedIn && this.hasLocalProfile) {
                return 'state-preview';
            }
            if (this.isLoggedIn && !this.hasLocalProfile) {
                return 'state-incomplete';
            }
            if (score < userMatchThreshold) {
                return 'state-below-threshold';
            }
            return '';
        }

        fetchMatches(jobIds) {
            if (jobIds.length === 0) return;

            // Check cache first
            const uncachedIds = jobIds.filter(id => !this.cache.has(id));

            if (uncachedIds.length === 0) {
                // All cached, just render
                jobIds.forEach(id => this.updateChart(id, this.cache.get(id)));
                return;
            }

            // Fetch from server
            $.ajax({
                url: window.sffc_ajax?.ajax_url || window.sffcJobBoard?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_batch_calculate_matches',
                    job_ids: uncachedIds,
                    nonce: window.sffc_ajax?.nonce || window.sffcJobBoard?.nonce || ''
                },
                success: (response) => {
                    if (response.success && response.data.matches) {
                        // Update threshold if provided
                        if (response.data.threshold) {
                            userMatchThreshold = response.data.threshold;
                        }

                        // Cache and render each match
                        Object.entries(response.data.matches).forEach(([jobId, matchData]) => {
                            this.cache.set(parseInt(jobId), matchData);
                            this.updateChart(jobId, matchData);
                        });
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error fetching matches:', error);
                    // Remove loading state
                    uncachedIds.forEach(id => {
                        $(`.sffc-match-donut[data-job-id="${id}"]`).removeClass('loading');
                    });
                }
            });
        }

        updateChart(jobId, matchData) {
            const $donut = $(`.sffc-match-donut[data-job-id="${jobId}"]`);
            if ($donut.length === 0) return;

            const score = Math.round(matchData.score || matchData.overall_score || 0);
            const breakdown = matchData.breakdown || matchData.category_scores || {
                skills: matchData.technical_skills || 0,
                experience: matchData.experience || 0,
                industry: matchData.industry_domain || 0,
                location: matchData.location || 0
            };

            // Normalize breakdown keys
            const normalizedBreakdown = {
                skills: breakdown.technical_skills || breakdown.skills || 0,
                experience: breakdown.experience || 0,
                industry: breakdown.industry_domain || breakdown.industry || 0,
                location: breakdown.location || 0
            };

            // Update state class
            $donut.removeClass('loading state-locked state-preview state-incomplete state-below-threshold');
            $donut.addClass(this.getStateClass(score));

            // Update score display
            $donut.find('.donut-score').text(score + '%');
            $donut.data('score', score);
            $donut.attr('aria-label', `Match score: ${score}%`);

            // Update segments with animation
            const segments = this.calculateSegments(normalizedBreakdown);

            Object.entries(segments).forEach(([category, values]) => {
                const $segment = $donut.find(`.segment-${category}`);
                $segment.css({
                    'stroke-dasharray': `${values.dash} ${values.gap}`,
                    'stroke-dashoffset': values.offset
                });
            });

            // Update tooltip
            $donut.find('.sffc-match-tooltip').replaceWith(this.renderTooltip(normalizedBreakdown, score));
        }

        clearCache() {
            this.cache.clear();
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.matchDonutRenderer = new MatchDonutRenderer();
    });

    // Handle click on incomplete state to open profile builder
    $(document).on('click', '.sffc-match-donut.state-incomplete', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof window.openProfileBuilder === 'function') {
            window.openProfileBuilder();
        }
    });

    // Handle click on locked state to show auth prompt
    $(document).on('click', '.sffc-match-donut.state-locked', function(e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof window.showPremiumAuthForm === 'function') {
            window.showPremiumAuthForm();
        }
    });

    // Handle click on preview state to show login prompt with score teaser
    $(document).on('click', '.sffc-match-donut.state-preview', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const score = $(this).data('score');
        // Show auth form with score context
        if (typeof window.showPremiumAuthForm === 'function') {
            window.showPremiumAuthForm();
        }
    });

    // Mobile: tap to show tooltip
    if ('ontouchstart' in window) {
        $(document).on('touchstart', '.sffc-match-donut', function(e) {
            const $donut = $(this);
            const $tooltip = $donut.find('.sffc-match-tooltip');

            // Toggle tooltip visibility
            if ($tooltip.css('visibility') === 'visible') {
                $tooltip.css({ opacity: 0, visibility: 'hidden' });
            } else {
                // Hide all other tooltips
                $('.sffc-match-tooltip').css({ opacity: 0, visibility: 'hidden' });
                $tooltip.css({ opacity: 1, visibility: 'visible' });
            }
        });

        // Close tooltip when tapping elsewhere
        $(document).on('touchstart', function(e) {
            if (!$(e.target).closest('.sffc-match-donut').length) {
                $('.sffc-match-tooltip').css({ opacity: 0, visibility: 'hidden' });
            }
        });
    }

})(jQuery);
