/**
 * Search Query Cleanup Admin JavaScript
 * 
 * Handles admin interface interactions for the search query cleanup system
 */

jQuery(document).ready(function($) {
    
    // Manual cleanup button
    $('#sffc-manual-cleanup').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Running Cleanup...');
        $('#cleanup-progress').show();
        $('#cleanup-results').hide();
        
        $.ajax({
            url: sffcCleanup.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_manual_search_cleanup',
                nonce: sffcCleanup.nonce
            },
            timeout: 300000, // 5 minutes
            success: function(response) {
                if (response.success) {
                    displayCleanupResults(response.data);
                    refreshStats();
                } else {
                    displayError('Cleanup failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                displayError('Network error during cleanup. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                $('#cleanup-progress').hide();
            }
        });
    });
    
    // Emergency cleanup button
    $('#sffc-emergency-cleanup').on('click', function() {
        if (!confirm('Emergency cleanup will quickly remove the most problematic queries. Continue?')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Running Emergency Cleanup...');
        $('#cleanup-progress').show();
        $('#cleanup-results').hide();
        
        $.ajax({
            url: sffcCleanup.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_emergency_cleanup',
                nonce: sffcCleanup.nonce
            },
            timeout: 60000, // 1 minute
            success: function(response) {
                if (response.success) {
                    displayCleanupResults(response.data);
                    refreshStats();
                } else {
                    displayError('Emergency cleanup failed: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                displayError('Network error during emergency cleanup. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
                $('#cleanup-progress').hide();
            }
        });
    });
    
    // Refresh statistics button
    $('#sffc-refresh-stats').on('click', function() {
        refreshStats();
    });
    
    /**
     * Display cleanup results
     */
    function displayCleanupResults(data) {
        let html = '<div class="cleanup-result">';
        html += '<h3>Cleanup Results</h3>';
        
        if (data.removed !== undefined) {
            // Emergency cleanup result
            html += '<p><strong>' + data.removed + '</strong> queries removed</p>';
            html += '<p>' + data.message + '</p>';
        } else {
            // Full cleanup results
            const total = (data.removed_repetitive || 0) + 
                         (data.removed_non_seo || 0) + 
                         (data.removed_spam || 0) + 
                         (data.removed_old || 0);
                         
            html += '<p><strong>Total Removed:</strong> ' + total + ' queries</p>';
            html += '<ul>';
            html += '<li>Repetitive queries: ' + (data.removed_repetitive || 0) + '</li>';
            html += '<li>Non-SEO queries: ' + (data.removed_non_seo || 0) + '</li>';
            html += '<li>Spam queries: ' + (data.removed_spam || 0) + '</li>';
            html += '<li>Old queries: ' + (data.removed_old || 0) + '</li>';
            html += '</ul>';
            
            if (data.optimized_table) {
                html += '<p>✓ Database table optimized</p>';
            }
        }
        
        html += '</div>';
        
        $('#cleanup-results').html(html).show();
    }
    
    /**
     * Display error message
     */
    function displayError(message) {
        const html = '<div class="cleanup-error"><strong>Error:</strong> ' + message + '</div>';
        $('#cleanup-results').html(html).show();
    }
    
    /**
     * Refresh statistics display
     */
    function refreshStats() {
        $.ajax({
            url: sffcCleanup.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_get_cleanup_stats',
                nonce: sffcCleanup.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }
    
    /**
     * Update statistics in the page
     */
    function updateStatsDisplay(stats) {
        // Update the statistics table
        $('table.wp-list-table tbody tr').each(function() {
            const label = $(this).find('td:first strong').text();
            const valueCell = $(this).find('td:last');
            
            switch(label) {
                case 'Total Queries':
                    valueCell.text(numberFormat(stats.total_queries));
                    break;
                case 'Queries (Last 7 days)':
                    valueCell.text(numberFormat(stats.last_7_days));
                    break;
                case 'Queries (Last 30 days)':
                    valueCell.text(numberFormat(stats.last_30_days));
                    break;
                case 'Potential Duplicates':
                    valueCell.text(numberFormat(stats.potential_duplicates));
                    break;
                case 'Estimated Non-SEO Queries':
                    valueCell.text(numberFormat(stats.estimated_non_seo));
                    break;
                case 'Table Size':
                    valueCell.text(stats.table_size_mb + ' MB');
                    break;
                case 'Last Cleanup':
                    valueCell.text(stats.last_cleanup);
                    break;
            }
        });
        
        // Update notice
        $('.notice-info p').html(
            '<strong>Current Database Status:</strong> ' + 
            numberFormat(stats.total_queries) + ' total search queries, ' +
            stats.table_size_mb + ' MB table size'
        );
    }
    
    /**
     * Format number with commas
     */
    function numberFormat(num) {
        return parseInt(num).toLocaleString();
    }
    
    // Auto-refresh stats every 30 seconds during cleanup
    let refreshInterval;
    
    function startAutoRefresh() {
        refreshInterval = setInterval(refreshStats, 30000);
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }
    
    // Start auto-refresh when cleanup begins
    $('#sffc-manual-cleanup, #sffc-emergency-cleanup').on('click', function() {
        startAutoRefresh();
        
        // Stop after 5 minutes
        setTimeout(stopAutoRefresh, 300000);
    });
});