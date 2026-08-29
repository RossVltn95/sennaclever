/**
 * Intelligence Dashboard Admin Scripts
 */
(function($) {
    'use strict';

    var Dashboard = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#sffc-budget-form').on('submit', this.updateBudget.bind(this));
            $('#sffc-clear-benchmark-cache').on('click', this.clearBenchmarkCache.bind(this));
            $('#sffc-clear-classification-cache').on('click', this.clearClassificationCache.bind(this));
            $('#sffc-process-batch').on('click', this.processBatch.bind(this));
        },

        showMessage: function(message, type) {
            var $message = $('<div class="sffc-message ' + type + '">' + message + '</div>');
            $('.sffc-intelligence-dashboard h1').after($message);

            setTimeout(function() {
                $message.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        updateBudget: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();

            $button.text('Saving...').prop('disabled', true);

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_update_budget',
                    nonce: sffcDashboard.nonce,
                    daily_budget: $form.find('input[name="daily_budget"]').val(),
                    monthly_budget: $form.find('input[name="monthly_budget"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.showMessage(response.data.message, 'success');
                    } else {
                        Dashboard.showMessage(response.data.message || 'Error updating budget', 'error');
                    }
                },
                error: function() {
                    Dashboard.showMessage('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        clearBenchmarkCache: function(e) {
            var $button = $(e.target);
            var originalText = $button.text();

            if (!confirm('Are you sure you want to clear the benchmark cache?')) {
                return;
            }

            $button.text('Clearing...').prop('disabled', true);

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_clear_caches',
                    nonce: sffcDashboard.nonce,
                    cache_type: 'benchmarks'
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.showMessage(response.data.message, 'success');
                    } else {
                        Dashboard.showMessage(response.data.message || 'Error clearing cache', 'error');
                    }
                },
                error: function() {
                    Dashboard.showMessage('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        clearClassificationCache: function(e) {
            var $button = $(e.target);
            var originalText = $button.text();

            if (!confirm('Are you sure? This will force re-classification of all articles on their next update.')) {
                return;
            }

            $button.text('Clearing...').prop('disabled', true);

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_clear_caches',
                    nonce: sffcDashboard.nonce,
                    cache_type: 'classifications'
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.showMessage(response.data.message, 'success');
                    } else {
                        Dashboard.showMessage(response.data.message || 'Error clearing cache', 'error');
                    }
                },
                error: function() {
                    Dashboard.showMessage('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        processBatch: function(e) {
            var $button = $(e.target);
            var originalText = $button.text();

            $button.text('Processing...').prop('disabled', true);

            $.ajax({
                url: sffcDashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_process_batch',
                    nonce: sffcDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        Dashboard.showMessage(response.data.message, 'success');
                        // Reload page to refresh queue display
                        if (response.data.remaining === 0) {
                            location.reload();
                        }
                    } else {
                        Dashboard.showMessage(response.data.message || 'Error processing batch', 'error');
                    }
                },
                error: function() {
                    Dashboard.showMessage('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        Dashboard.init();
    });

})(jQuery);
