/**
 * Job Landing Page - Accordion Interactions
 * Handles expand/collapse, filter clicks, and signup triggers
 */

(function($) {
    'use strict';

    var JobLanding = {

        init: function() {
            this.bindEvents();
            this.autoExpandFirst();
        },

        bindEvents: function() {
            var self = this;

            // Row header click → expand/collapse
            $(document).on('click', '.sffc-job-landing-header', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.sffc-job-landing-row');
                self.toggleRow($row);
            });

            // Filter clicks → signup modal if logged out
            $(document).on('click', '.sffc-job-landing-filter', function(e) {
                e.preventDefault();

                if (!sffcJobLanding.isLoggedIn) {
                    self.showSignupPrompt('filter');
                } else {
                    // For logged-in users, could implement actual filtering here
                    alert('Filtering feature coming soon!');
                }
            });

            // Signup trigger buttons
            $(document).on('click', '.sffc-job-landing-signup-trigger', function(e) {
                e.preventDefault();
                self.showSignupPrompt('button');
            });

            // Blurred score clicks → signup
            $(document).on('click', '.sffc-job-landing-score--blurred', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showSignupPrompt('score');
            });
        },

        autoExpandFirst: function() {
            // First row already has expanded class from PHP
            // Just ensure content is visible
            $('.sffc-job-landing-row:first').addClass('sffc-job-landing-row--expanded');
        },

        toggleRow: function($row) {
            var isExpanded = $row.hasClass('sffc-job-landing-row--expanded');

            // Collapse all rows
            $('.sffc-job-landing-row').removeClass('sffc-job-landing-row--expanded');

            // Expand clicked row (unless it was already expanded)
            if (!isExpanded) {
                $row.addClass('sffc-job-landing-row--expanded');

                // Smooth scroll to keep header in view
                this.scrollToRow($row);
            }
        },

        scrollToRow: function($row) {
            var headerOffset = 100;
            var rowTop = $row.offset().top - headerOffset;

            $('html, body').animate({
                scrollTop: rowTop
            }, 300);
        },

        showSignupPrompt: function(source) {
            // Track where signup was triggered from
            console.log('[Job Landing] Signup triggered from:', source);

            // Redirect to CRM with signup prompt
            var redirectUrl = sffcJobLanding.crmUrl + '?signup=1&source=job_landing&trigger=' + source;
            window.location.href = redirectUrl;
        }

    };

    // Initialize on document ready
    $(document).ready(function() {
        JobLanding.init();
    });

})(jQuery);
