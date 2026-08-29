/**
 * Intelligence Brief Frontend Handler
 *
 * Handles the generation and download of intelligence briefs.
 */

(function($) {
    'use strict';

    const IntelligenceBrief = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.checkStatus();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $(document).on('click', '.sffc-download-brief-btn', this.handleDownload.bind(this));
            $(document).on('click', '.sffc-generate-brief-btn', this.handleGenerate.bind(this));
        },

        /**
         * Check brief status on page load
         */
        checkStatus: function() {
            if (!sffcBrief.postId) return;

            $.ajax({
                url: sffcBrief.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_check_brief_status',
                    nonce: sffcBrief.nonce,
                    post_id: sffcBrief.postId
                },
                success: function(response) {
                    if (response.success) {
                        IntelligenceBrief.updateButtonState(response.data);
                    }
                }
            });
        },

        /**
         * Update button state based on status
         */
        updateButtonState: function(status) {
            const $btn = $('.sffc-download-brief-btn, .sffc-generate-brief-btn');

            if (status.is_complete) {
                $btn.removeClass('disabled loading')
                    .addClass('ready')
                    .find('.btn-text').text('Download Intelligence Brief');
                $btn.find('.btn-icon').html(this.getDownloadIcon());
            } else if (status.has_intelligence) {
                $btn.removeClass('disabled loading')
                    .addClass('incomplete')
                    .find('.btn-text').text('Generate Brief (' + Math.round(status.completeness_score) + '% ready)');
            }
        },

        /**
         * Handle download button click
         */
        handleDownload: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);

            if ($btn.hasClass('loading')) return;

            this.setLoading($btn, sffcBrief.strings.generating);

            $.ajax({
                url: sffcBrief.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_generate_intelligence_brief',
                    nonce: sffcBrief.nonce,
                    post_id: sffcBrief.postId
                },
                success: function(response) {
                    if (response.success && response.data.download_url) {
                        IntelligenceBrief.setLoading($btn, sffcBrief.strings.downloading);

                        // Trigger download
                        window.location.href = response.data.download_url;

                        setTimeout(function() {
                            IntelligenceBrief.resetButton($btn);
                        }, 2000);
                    } else {
                        IntelligenceBrief.showError($btn, response.data.message || sffcBrief.strings.error);
                    }
                },
                error: function() {
                    IntelligenceBrief.showError($btn, sffcBrief.strings.error);
                }
            });
        },

        /**
         * Handle generate button click (when brief isn't ready)
         */
        handleGenerate: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);

            if ($btn.hasClass('loading')) return;

            this.setLoading($btn, sffcBrief.strings.enriching);

            $.ajax({
                url: sffcBrief.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_generate_intelligence_brief',
                    nonce: sffcBrief.nonce,
                    post_id: sffcBrief.postId
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.is_complete) {
                            IntelligenceBrief.setLoading($btn, sffcBrief.strings.downloading);
                            window.location.href = response.data.download_url;

                            setTimeout(function() {
                                IntelligenceBrief.resetButton($btn);
                            }, 2000);
                        } else {
                            IntelligenceBrief.showIncomplete($btn, response.data.missing);
                        }
                    } else {
                        IntelligenceBrief.showError($btn, response.data.message || sffcBrief.strings.error);
                    }
                },
                error: function() {
                    IntelligenceBrief.showError($btn, sffcBrief.strings.error);
                }
            });
        },

        /**
         * Set button to loading state
         */
        setLoading: function($btn, text) {
            $btn.addClass('loading')
                .removeClass('ready incomplete error');

            $btn.find('.btn-text').text(text);
            $btn.find('.btn-icon').html(this.getSpinnerIcon());
        },

        /**
         * Reset button to default state
         */
        resetButton: function($btn) {
            $btn.removeClass('loading error incomplete')
                .addClass('ready');

            $btn.find('.btn-text').text('Download Intelligence Brief');
            $btn.find('.btn-icon').html(this.getDownloadIcon());
        },

        /**
         * Show error state
         */
        showError: function($btn, message) {
            $btn.removeClass('loading ready incomplete')
                .addClass('error');

            $btn.find('.btn-text').text(message);
            $btn.find('.btn-icon').html(this.getErrorIcon());

            setTimeout(function() {
                IntelligenceBrief.resetButton($btn);
            }, 3000);
        },

        /**
         * Show incomplete state
         */
        showIncomplete: function($btn, missing) {
            $btn.removeClass('loading ready error')
                .addClass('incomplete');

            const missingText = missing.length > 0
                ? 'Missing: ' + missing.slice(0, 2).join(', ')
                : sffcBrief.strings.incomplete;

            $btn.find('.btn-text').text(missingText);
            $btn.find('.btn-icon').html(this.getWarningIcon());

            setTimeout(function() {
                IntelligenceBrief.resetButton($btn);
            }, 5000);
        },

        /**
         * Get download icon SVG
         */
        getDownloadIcon: function() {
            return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>';
        },

        /**
         * Get spinner icon SVG
         */
        getSpinnerIcon: function() {
            return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><circle cx="12" cy="12" r="10" stroke-dasharray="32" stroke-dashoffset="32"><animate attributeName="stroke-dashoffset" values="32;0" dur="1s" repeatCount="indefinite"/></circle></svg>';
        },

        /**
         * Get error icon SVG
         */
        getErrorIcon: function() {
            return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
        },

        /**
         * Get warning icon SVG
         */
        getWarningIcon: function() {
            return '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        IntelligenceBrief.init();
    });

})(jQuery);
