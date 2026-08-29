/**
 * Admin Dashboard JavaScript
 * 
 * Handles AJAX interactions for the Template Intelligence System dashboard
 * 
 * @package SennaFinanceCareer
 */

jQuery(document).ready(function($) {
    
    // Refresh PE Insights data
    $('#refresh-pe-insights').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Refreshing...');
        
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_refresh_pe_insights',
                nonce: sffcAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('PE Insights data refreshed successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('Failed to refresh PE Insights: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while refreshing PE Insights', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test Claude connection
    $('#test-claude').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_test_claude',
                nonce: sffcAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Claude API connection successful', 'success');
                } else {
                    showNotice('Claude API test failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while testing Claude API', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Refresh templates
    $('#refresh-templates').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Refreshing...');
        
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_refresh_templates',
                nonce: sffcAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Templates refreshed successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('Failed to refresh templates: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while refreshing templates', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Run system health check
    $('#system-health-check').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Checking...');
        
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_system_health_check',
                nonce: sffcAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayHealthCheckResults(response.data);
                } else {
                    showNotice('Health check failed: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error during health check', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Refresh all data
    $('#refresh-all-data').on('click', function() {
        if (!confirm('This will refresh all templates and data. This may take several minutes. Continue?')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Refreshing All...');
        showNotice('Starting comprehensive data refresh...', 'info');
        
        // Chain multiple refresh operations
        refreshAllDataSequence()
            .then(() => {
                showNotice('All data refreshed successfully', 'success');
                setTimeout(() => location.reload(), 2000);
            })
            .catch((error) => {
                showNotice('Some refresh operations failed: ' + error, 'error');
            })
            .finally(() => {
                button.prop('disabled', false).text(originalText);
            });
    });
    
    // Clear all cache
    $('#clear-all-cache').on('click', function() {
        if (!confirm('This will clear all cached data. Continue?')) {
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_clear_cache',
                nonce: sffcAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('All cache cleared successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice('Failed to clear cache: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotice('Connection error while clearing cache', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Export system log
    $('#export-system-log').on('click', function() {
        const debugInfo = $('.sffc-debug-info textarea').val();
        const blob = new Blob([debugInfo], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sffc-system-log-' + new Date().toISOString().split('T')[0] + '.txt';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showNotice('System log exported successfully', 'success');
    });
    
    // Reset system
    $('#reset-system').on('click', function() {
        if (!confirm('This will reset the entire Template Intelligence System. All cached data and settings will be cleared. This action cannot be undone. Continue?')) {
            return;
        }
        
        const secondConfirm = prompt('Type "RESET" to confirm this action:');
        if (secondConfirm !== 'RESET') {
            showNotice('Reset cancelled', 'info');
            return;
        }
        
        const button = $(this);
        const originalText = button.text();
        
        button.prop('disabled', true).text('Resetting...');
        
        // Clear cache first, then reload
        $.ajax({
            url: sffcAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_clear_cache',
                nonce: sffcAdmin.nonce
            },
            success: function() {
                showNotice('System reset complete', 'success');
                setTimeout(() => location.reload(), 1500);
            },
            error: function() {
                showNotice('Reset failed', 'error');
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    /**
     * Chain all refresh operations
     */
    function refreshAllDataSequence() {
        return new Promise((resolve, reject) => {
            const operations = [
                { action: 'sffc_refresh_pe_insights', message: 'PE Insights data' },
                { action: 'sffc_refresh_templates', message: 'Templates' }
            ];
            
            let completed = 0;
            let errors = [];
            
            operations.forEach((op, index) => {
                setTimeout(() => {
                    $.ajax({
                        url: sffcAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: op.action,
                            nonce: sffcAdmin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotice(op.message + ' refreshed', 'success');
                            } else {
                                errors.push(op.message + ': ' + response.data);
                            }
                        },
                        error: function() {
                            errors.push(op.message + ': Connection error');
                        },
                        complete: function() {
                            completed++;
                            if (completed === operations.length) {
                                if (errors.length > 0) {
                                    reject(errors.join('; '));
                                } else {
                                    resolve();
                                }
                            }
                        }
                    });
                }, index * 2000); // Stagger operations by 2 seconds
            });
        });
    }
    
    /**
     * Display health check results
     */
    function displayHealthCheckResults(results) {
        let message = 'System Health Check Results:\n\n';
        
        for (const [component, status] of Object.entries(results)) {
            const statusText = status === 'healthy' ? '✓ Healthy' : '✗ ' + status;
            message += `${component}: ${statusText}\n`;
        }
        
        alert(message);
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type = 'info') {
        // Remove existing notices
        $('.sffc-admin-notice').remove();
        
        const noticeClass = type === 'error' ? 'notice-error' : 
                           type === 'success' ? 'notice-success' : 
                           type === 'warning' ? 'notice-warning' : 'notice-info';
        
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible sffc-admin-notice">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // Auto-dismiss success notices
        if (type === 'success') {
            setTimeout(() => {
                notice.fadeOut();
            }, 3000);
        }
        
        // Handle dismiss button
        notice.on('click', '.notice-dismiss', function() {
            notice.fadeOut();
        });
        
        // Scroll to top to show notice
        $('html, body').animate({ scrollTop: 0 }, 300);
    }
    
    /**
     * Auto-refresh status indicators every 30 seconds
     */
    setInterval(function() {
        // Only refresh if page is visible and no operations are running
        if (!document.hidden && !$('button:disabled').length) {
            $.ajax({
                url: sffcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_get_status_update',
                    nonce: sffcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateStatusIndicators(response.data);
                    }
                },
                error: function() {
                    // Silently fail for background updates
                }
            });
        }
    }, 30000);
    
    /**
     * Update status indicators
     */
    function updateStatusIndicators(statusData) {
        // Update timestamp displays
        $('.activity-timestamp').each(function() {
            const timestamp = $(this).data('timestamp');
            if (timestamp) {
                $(this).text(formatTimestamp(timestamp));
            }
        });
        
        // Update any real-time metrics
        if (statusData.cache_usage) {
            $('.status-details:contains("Cache Usage")').find('strong').text(statusData.cache_usage + '%');
        }
    }
    
    /**
     * Format timestamp for display
     */
    function formatTimestamp(timestamp) {
        const now = Date.now() / 1000;
        const diff = now - timestamp;
        
        if (diff < 60) {
            return 'Just now';
        } else if (diff < 3600) {
            return Math.floor(diff / 60) + ' minutes ago';
        } else if (diff < 86400) {
            return Math.floor(diff / 3600) + ' hours ago';
        } else {
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    }
    
});