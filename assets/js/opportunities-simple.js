/**
 * SFFC Opportunities Simple
 * Basic opportunities display without shortlist functionality
 */

(function($) {
    'use strict';
    
    // Initialize opportunities when page loads
    $(document).ready(function() {
        console.log('SFFC: Opportunities Simple loaded');
        
        // Initialize opportunity cards
        initializeOpportunityCards();
        
        // Handle interested buttons 
        bindInterestedButtons();
    });
    
    /**
     * Initialize opportunity cards
     */
    function initializeOpportunityCards() {
        console.log('SFFC: Initializing opportunity cards');
        
        // Auto-load opportunities if container exists
        const opportunitiesContainer = $('.sffc-opportunities-container, .ultimate-opportunities');
        if (opportunitiesContainer.length) {
            loadOpportunities();
        }
        
        // Initialize message overlays
        setTimeout(() => {
            initializeMessageOverlays();
        }, 1000);
    }
    
    /**
     * Load opportunities from backend
     */
    async function loadOpportunities() {
        try {
            const response = await $.ajax({
                url: window.sffc_ajax?.url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_get_opportunities',
                    nonce: window.sffc_ajax?.nonce || ''
                }
            });
            
            if (response.success && response.data) {
                renderOpportunities(response.data);
            }
        } catch (error) {
            console.error('Failed to load opportunities:', error);
        }
    }
    
    /**
     * Render opportunities to the page
     */
    function renderOpportunities(opportunities) {
        const container = $('.sffc-opportunities-container, .ultimate-opportunities');
        if (!container.length) return;
        
        const html = opportunities.map(job => createJobCard(job)).join('');
        container.html(html);
        
        // Re-bind event handlers
        bindInterestedButtons();
    }
    
    /**
     * Create individual job card HTML
     */
    function createJobCard(job) {
        const matchScore = job.match_score || calculateBasicMatchScore(job);
        const salaryDisplay = formatSalary(job);
        
        return `
            <div class="sffc-job-card" data-job-id="${job.id}">
                <div class="sffc-job-header">
                    <h3 class="sffc-job-title">${job.title}</h3>
                    <div class="sffc-company">${job.company}</div>
                </div>
                
                <div class="sffc-job-meta">
                    <span class="sffc-location">${job.location || 'Location not specified'}</span>
                    <span class="sffc-salary">${salaryDisplay}</span>
                    <span class="sffc-match-score">${matchScore}% match</span>
                </div>
                
                <div class="sffc-job-description">
                    ${getJobSummary(job)}
                </div>
                
                <div class="sffc-job-actions">
                    <button class="sffc-action-btn sffc-btn-interested" 
                            data-job-id="${job.id}">
                        Interested
                    </button>
                    <button class="sffc-action-btn sffc-btn-analyze" 
                            data-job-id="${job.id}">
                        Analyze
                    </button>
                </div>
            </div>
        `;
    }
    
    /**
     * Calculate basic match score
     */
    function calculateBasicMatchScore(job) {
        // Simple scoring based on job content
        const isLoggedIn = window.sffc_frontend?.is_logged_in === '1';
        
        if (!isLoggedIn) {
            return 'Login to see';
        }
        
        // Basic score calculation
        let score = 60;
        
        if (job.title) {
            // Boost for certain keywords
            const keywords = ['analyst', 'manager', 'coordinator', 'specialist'];
            keywords.forEach(keyword => {
                if (job.title.toLowerCase().includes(keyword)) {
                    score += 5;
                }
            });
        }
        
        return Math.min(score, 85);
    }
    
    /**
     * Format salary display
     */
    function formatSalary(job) {
        if (job.salary_display) return job.salary_display;
        if (job.salary_min && job.salary_max) {
            return `$${formatNumber(job.salary_min)} - $${formatNumber(job.salary_max)}`;
        }
        if (job.salary_min) return `$${formatNumber(job.salary_min)}+`;
        if (job.salary_max) return `Up to $${formatNumber(job.salary_max)}`;
        return 'Competitive';
    }
    
    /**
     * Format number for display
     */
    function formatNumber(num) {
        if (num >= 1000) {
            return Math.round(num / 1000) + 'k';
        }
        return num.toString();
    }
    
    /**
     * Get job summary
     */
    function getJobSummary(job) {
        if (job.description) {
            const summary = job.description.substring(0, 150);
            return summary + (job.description.length > 150 ? '...' : '');
        }
        return 'Explore this opportunity to advance your career.';
    }
    
    /**
     * Bind interested button events
     */
    function bindInterestedButtons() {
        $('.sffc-btn-interested').off('click.simple').on('click.simple', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const jobId = $btn.data('job-id');
            
            // Toggle button state
            if ($btn.hasClass('added')) {
                $btn.removeClass('added').text('Interested');
                showNotification('Removed from saved jobs', 'info');
            } else {
                $btn.addClass('added').text('Added');
                showNotification('Added to saved jobs!', 'success');
            }
        });
        
        $('.sffc-btn-analyze').off('click.simple').on('click.simple', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const jobId = $(this).data('job-id');
            
            // Open analysis (if available)
            if (window.SennaChat) {
                window.SennaChat.open();
                showNotification('Opening analysis...', 'info');
            } else {
                showNotification('Analysis feature coming soon', 'info');
            }
        });
    }
    
    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="sffc-notification ${type}">
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.addClass('show');
        }, 10);
        
        setTimeout(() => {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
    
    /**
     * Initialize message overlays for better UX
     */
    function initializeMessageOverlays() {
        // Add subtle overlays to enhance the experience
        const cards = $('.sffc-job-card');
        
        cards.each(function() {
            const $card = $(this);
            
            $card.on('mouseenter', function() {
                $(this).addClass('hover');
            });
            
            $card.on('mouseleave', function() {
                $(this).removeClass('hover');
            });
        });
    }
    
    // Export functions for global use
    window.bindInterestedButtons = bindInterestedButtons;
    
})(jQuery);