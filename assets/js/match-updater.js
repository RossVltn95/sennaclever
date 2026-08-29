/**
 * Match Updater JavaScript
 * Updates job match scores in real-time when profile changes
 * Integrates with profile builder and opportunities display
 */

jQuery(document).ready(function($) {
    console.log('SFFC Match Updater: Initializing...');
    
    // State
    let matchCache = {};
    let isUpdating = false;
    
    // Initialize
    init();
    
    /**
     * Initialize match updater
     */
    function init() {
        // Check if we have job cards to update
        if ($('.sffc-opportunity-card').length > 0) {
            console.log('SFFC Match Updater: Found job cards, calculating matches...');
            calculateInitialMatches();
        }
        
        // Listen for profile updates
        $(document).on('sffc:profile:updated', function(e, profileData) {
            console.log('SFFC Match Updater: Profile updated, recalculating matches...');
            recalculateAllMatches();
        });
        
        // Listen for new opportunities loaded
        $(document).on('sffc:opportunities:loaded', function(e, opportunities) {
            console.log('SFFC Match Updater: New opportunities loaded, calculating matches...');
            calculateMatchesForOpportunities(opportunities);
        });
        
        // Bind hover events for match details
        bindMatchDetailEvents();
    }
    
    /**
     * Calculate initial matches for visible job cards
     */
    function calculateInitialMatches() {
        const $cards = $('.sffc-opportunity-card');
        const jobIds = [];
        
        $cards.each(function() {
            const jobId = $(this).data('job-id');
            if (jobId && !matchCache[jobId]) {
                jobIds.push(jobId);
                // Add loading indicator
                addLoadingIndicator($(this));
            }
        });
        
        if (jobIds.length > 0) {
            fetchMatchesBatch(jobIds);
        }
    }
    
    /**
     * Fetch matches for multiple jobs
     */
    function fetchMatchesBatch(jobIds) {
        if (isUpdating) return;
        
        isUpdating = true;
        
        $.ajax({
            url: sffc_match.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_get_job_matches_batch',
                job_ids: jobIds,
                nonce: sffc_match.nonce
            },
            success: function(response) {
                if (response.success && response.data.matches) {
                    console.log('SFFC Match Updater: Received matches', response.data.matches);
                    updateMatchBadges(response.data.matches);
                }
            },
            error: function(xhr, status, error) {
                console.error('SFFC Match Updater: Failed to fetch matches', error);
                // Remove loading indicators
                $('.sffc-match-loading').remove();
            },
            complete: function() {
                isUpdating = false;
            }
        });
    }
    
    /**
     * Update match badges on job cards
     */
    function updateMatchBadges(matches) {
        Object.keys(matches).forEach(jobId => {
            const match = matches[jobId];
            const $card = $(`.sffc-opportunity-card[data-job-id="${jobId}"]`);
            
            if ($card.length) {
                // Cache the match data
                matchCache[jobId] = match;
                
                // Remove loading indicator
                $card.find('.sffc-match-loading').remove();
                
                // Add or update match badge
                updateMatchBadge($card, match);
                
                // Add match details
                addMatchDetails($card, match);
            }
        });
    }
    
    /**
     * Update single match badge
     */
    function updateMatchBadge($card, match) {
        let $badge = $card.find('.sffc-match-indicator');
        
        const matchClass = getMatchClass(match.score);
        const badgeHtml = `
            <div class="sffc-match-indicator ${matchClass}" data-job-id="${$card.data('job-id')}">
                <span class="sffc-match-label">${match.strength}</span>
            </div>
        `;
        
        if ($badge.length) {
            // Update existing badge
            $badge.replaceWith(badgeHtml);
            $badge = $card.find('.sffc-match-indicator');
            $badge.addClass('updating');
            setTimeout(() => $badge.removeClass('updating'), 600);
        } else {
            // Add new badge
            const $header = $card.find('.sffc-company-header');
            if ($header.length) {
                $header.append(badgeHtml);
            } else {
                // Fallback: Add to card
                $card.prepend(badgeHtml);
            }
        }
    }
    
    /**
     * Add match details tooltip
     */
    function addMatchDetails($card, match) {
        // Remove existing details
        $card.find('.sffc-match-details').remove();
        
        if (match.reasons && match.reasons.length > 0) {
            const reasonsHtml = match.reasons.map(reason => 
                `<li class="sffc-match-reason">${reason}</li>`
            ).join('');
            
            const detailsHtml = `
                <div class="sffc-match-details">
                    <ul class="sffc-match-reasons">
                        ${reasonsHtml}
                    </ul>
                    ${match.score < 70 ? `
                        <div class="sffc-improve-profile-prompt">
                            <span>Complete your profile for better matches</span>
                            <a href="#" class="sffc-improve-profile-btn" onclick="openProfileBuilder(); return false;">
                                Improve Match
                            </a>
                        </div>
                    ` : ''}
                </div>
            `;
            
            $card.find('.sffc-match-indicator').after(detailsHtml);
        }
    }
    
    /**
     * Get match class based on score
     */
    function getMatchClass(score) {
        if (score >= 90) return 'perfect-match';
        if (score >= 80) return 'very-strong-match';
        if (score >= 70) return 'strong-match';
        if (score >= 60) return 'good-match';
        if (score >= 50) return 'moderate-match';
        return 'stretch-match';
    }
    
    /**
     * Add loading indicator to card
     */
    function addLoadingIndicator($card) {
        const loadingHtml = '<div class="sffc-match-loading"></div>';
        const $header = $card.find('.sffc-company-header');
        
        if ($header.length) {
            $header.append(loadingHtml);
        } else {
            $card.prepend(loadingHtml);
        }
    }
    
    /**
     * Recalculate all matches after profile update
     */
    function recalculateAllMatches() {
        // Clear cache to force recalculation
        matchCache = {};
        
        // Show updating state on all badges
        $('.sffc-match-indicator').addClass('updating');
        
        // Recalculate
        calculateInitialMatches();
    }
    
    /**
     * Calculate matches for newly loaded opportunities
     */
    function calculateMatchesForOpportunities(opportunities) {
        const jobIds = opportunities.map(opp => opp.id).filter(id => !matchCache[id]);
        
        if (jobIds.length > 0) {
            fetchMatchesBatch(jobIds);
        }
    }
    
    /**
     * Bind hover events for match details
     */
    function bindMatchDetailEvents() {
        // Show details on badge hover
        $(document).on('mouseenter', '.sffc-match-indicator', function() {
            const $details = $(this).siblings('.sffc-match-details');
            if ($details.length) {
                $details.addClass('visible');
            }
        });
        
        // Keep details visible when hovering over them
        $(document).on('mouseenter', '.sffc-match-details', function() {
            $(this).addClass('visible');
        });
        
        // Hide details when leaving both badge and details
        $(document).on('mouseleave', '.sffc-match-indicator, .sffc-match-details', function(e) {
            const $related = $(e.relatedTarget);
            if (!$related.hasClass('sffc-match-indicator') && !$related.hasClass('sffc-match-details')) {
                $('.sffc-match-details').removeClass('visible');
            }
        });
    }
    
    /**
     * Global function to reload opportunities with matches
     */
    window.reloadOpportunitiesWithMatches = function() {
        console.log('SFFC Match Updater: Reloading opportunities with updated matches...');
        
        // Clear match cache
        matchCache = {};
        
        // If opportunities script exists, trigger reload
        if (typeof window.loadOpportunities === 'function') {
            window.loadOpportunities();
        } else {
            // Otherwise just recalculate existing cards
            calculateInitialMatches();
        }
    };
    
    /**
     * Add no-profile badge for users without profiles
     */
    function addNoProfileBadge($card) {
        const badgeHtml = `
            <div class="sffc-no-profile-badge" onclick="openProfileBuilder()">
                Build Profile for Match Score
            </div>
        `;
        
        const $header = $card.find('.sffc-company-header');
        if ($header.length) {
            $header.append(badgeHtml);
        }
    }
    
    /**
     * Check if user has profile
     */
    function checkUserProfile() {
        $.ajax({
            url: sffc_profile.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_get_user_profile',
                nonce: sffc_profile.nonce
            },
            success: function(response) {
                if (response.success && response.data.profile) {
                    const profile = response.data.profile;
                    if (profile.completion < 20) {
                        // Show prompt to complete profile
                        $('.sffc-opportunity-card').each(function() {
                            if (!$(this).find('.sffc-match-indicator').length) {
                                addNoProfileBadge($(this));
                            }
                        });
                    }
                }
            }
        });
    }
    
    // Check profile on load
    if (typeof sffc_profile !== 'undefined') {
        checkUserProfile();
    }
});