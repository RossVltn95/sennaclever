/**
 * Search Administration JavaScript
 * Handles search index rebuilding and settings management
 */

(function($) {
    'use strict';
    
    // Initialize when document ready
    $(document).ready(function() {
        initSearchAdmin();
    });
    
    function initSearchAdmin() {
        // Rebuild index button
        $('#sffc-rebuild-index').on('click', handleRebuildIndex);
        
        // Check status button  
        $('#sffc-check-status').on('click', handleCheckStatus);
        
        // Settings form
        $('#sffc-search-settings-form').on('submit', handleSaveSettings);
        
        // Auto-refresh status every 30 seconds when rebuilding
        window.sffc_status_interval = null;
    }
    
    function handleRebuildIndex(e) {
        e.preventDefault();
        
        if (!confirm(sffc_search_admin.strings.confirm_rebuild)) {
            return;
        }
        
        const $button = $(this);
        const $progress = $('#sffc-rebuild-progress');
        const $results = $('#sffc-rebuild-results');
        
        // Show progress
        $button.prop('disabled', true).text('🔄 ' + sffc_search_admin.strings.rebuilding);
        $progress.show();
        $results.empty();
        
        // Start progress animation
        animateProgress();
        
        // Make AJAX request
        $.ajax({
            url: sffc_search_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_rebuild_search_index',
                nonce: sffc_search_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    updateStatus(response.data.status);
                } else {
                    showError(response.data || sffc_search_admin.strings.rebuild_error);
                }
            },
            error: function() {
                showError(sffc_search_admin.strings.rebuild_error);
            },
            complete: function() {
                $button.prop('disabled', false).text('🔄 Rebuild Search Index');
                $progress.hide();
                stopProgress();
            }
        });
    }
    
    function handleCheckStatus(e) {
        e.preventDefault();
        
        const $button = $(this);
        $button.prop('disabled', true);
        
        $.ajax({
            url: sffc_search_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_search_status',
                nonce: sffc_search_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatus(response.data);
                    showSuccess('Status updated!');
                }
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }
    
    function handleSaveSettings(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submit = $form.find('button[type="submit"]');
        const formData = $form.serialize();
        
        $submit.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: sffc_search_admin.ajax_url,
            type: 'POST',
            data: formData + '&action=sffc_save_search_settings',
            success: function(response) {
                if (response.success) {
                    showSuccess('Settings saved successfully!');
                } else {
                    showError('Error saving settings.');
                }
            },
            error: function() {
                showError('Error saving settings.');
            },
            complete: function() {
                $submit.prop('disabled', false).text('Save Settings');
            }
        });
    }
    
    function animateProgress() {
        const $fill = $('.sffc-progress-fill');
        const $text = $('.sffc-progress-text');
        
        let progress = 0;
        const steps = [
            'Preparing index...',
            'Processing content...',
            'Extracting entities...',
            'Calculating scores...',
            'Finalizing index...'
        ];
        
        window.sffc_progress_interval = setInterval(function() {
            progress += Math.random() * 10;
            
            if (progress > 90) {
                progress = 90;
            }
            
            $fill.css('width', progress + '%');
            
            const stepIndex = Math.floor(progress / 20);
            if (steps[stepIndex]) {
                $text.text(steps[stepIndex]);
            }
        }, 300);
    }
    
    function stopProgress() {
        if (window.sffc_progress_interval) {
            clearInterval(window.sffc_progress_interval);
        }
        
        $('.sffc-progress-fill').css('width', '100%');
        $('.sffc-progress-text').text('Complete!');
        
        setTimeout(function() {
            $('.sffc-progress-fill').css('width', '0%');
            $('.sffc-progress-text').text('Starting...');
        }, 2000);
    }
    
    function updateStatus(status) {
        // Update status numbers
        $('.sffc-stat-number').each(function() {
            const $stat = $(this);
            const $label = $stat.next('.sffc-stat-label');
            const label = $label.text();
            
            if (label.includes('Total Items')) {
                $stat.text(number_format(status.total_indexed));
            } else if (label.includes('Entities')) {
                $stat.text(number_format(status.entities_count));
            } else if (label.includes('Last Updated')) {
                $stat.text(status.last_updated);
            }
        });
        
        // Update post type breakdown
        $('.sffc-post-type-breakdown').find('.sffc-breakdown-item').each(function() {
            const $item = $(this);
            const type = $item.find('.sffc-breakdown-type').text();
            // This would need mapping from display names back to post types
            // For now, just refresh the page or implement more detailed updates
        });
    }
    
    function showSuccess(message) {
        showNotice(message, 'success');
    }
    
    function showError(message) {
        showNotice(message, 'error');
    }
    
    function showNotice(message, type) {
        const $results = $('#sffc-rebuild-results');
        const iconMap = {
            'success': '✅',
            'error': '❌'
        };
        
        $results.html(
            '<div class="notice notice-' + type + ' is-dismissible">' +
                '<p>' + iconMap[type] + ' ' + message + '</p>' +
            '</div>'
        );
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $results.find('.notice').fadeOut();
        }, 5000);
    }
    
    function number_format(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
})(jQuery);