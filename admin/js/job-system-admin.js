/**
 * Job System Admin JavaScript
 * Handles all button clicks and AJAX operations
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Helper function to show results
    function showResult(message, type = 'info') {
        const $results = $('#action-results');
        const $message = $('#action-message');
        
        $results.removeClass('notice-info notice-success notice-error')
            .addClass('notice-' + type)
            .show();
        
        $message.html(message);
        
        // Auto-hide after 10 seconds
        setTimeout(function() {
            $results.fadeOut();
        }, 10000);
    }
    
    // Helper function to handle button loading state
    function setButtonLoading($button, loading) {
        if (loading) {
            $button.addClass('loading').prop('disabled', true);
            $button.data('original-text', $button.html());
            $button.html('<span class="spinner is-active"></span> Processing...');
        } else {
            $button.removeClass('loading').prop('disabled', false);
            const originalText = $button.data('original-text');
            if (originalText) {
                $button.html(originalText);
            }
        }
    }
    
    // Create Queue Table
    $('#create-queue-table').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_create_queue_table',
                nonce: sffcJobAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showResult(response.data.message, 'success');
                    $button.replaceWith('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Table Created');
                    
                    // Refresh page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showResult(response.data.message || 'Failed to create table', 'error');
                }
            },
            error: function() {
                showResult('Network error occurred', 'error');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Test Fetchers
    $('#test-fetchers').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_test_job_fetchers',
                nonce: sffcJobAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    let message = '<strong>Fetcher Test Results:</strong><br>';
                    
                    if (response.data.workday) {
                        const status = response.data.workday.success ? '✅' : '❌';
                        message += status + ' Workday: ' + response.data.workday.message + '<br>';
                    }
                    
                    if (response.data.xml) {
                        const status = response.data.xml.success ? '✅' : '❌';
                        message += status + ' XML: ' + response.data.xml.message;
                    }
                    
                    showResult(message, response.data.workday?.success || response.data.xml?.success ? 'success' : 'error');
                } else {
                    showResult('Test failed: ' + (response.data?.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showResult('Network error: ' + error, 'error');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Manual Fetch Jobs
    $('#manual-fetch').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        
        if (!confirm('This will fetch up to 10 jobs from all sources. Continue?')) {
            return;
        }
        
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_manual_fetch_jobs',
                nonce: sffcJobAdmin.nonce
            },
            timeout: 60000, // 60 second timeout for fetching
            success: function(response) {
                if (response.success) {
                    showResult(response.data.message, 'success');
                    
                    // Refresh the page after 3 seconds to show new jobs
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    showResult('Fetch failed: ' + (response.data?.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showResult('Request timed out. Jobs may still be processing in the background.', 'warning');
                } else {
                    showResult('Network error: ' + error, 'error');
                }
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Process Queue Now
    $('#process-queue-now').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_process_queue_now',
                nonce: sffcJobAdmin.nonce
            },
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    showResult(response.data.message, 'success');
                    
                    // Update queue stats if provided
                    if (response.data.stats) {
                        // Refresh page to show updated stats
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    showResult('Processing failed: ' + (response.data?.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showResult('Processing is taking longer than expected. Check back in a moment.', 'warning');
                } else {
                    showResult('Network error: ' + error, 'error');
                }
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Flush Permalinks
    $('#flush-permalinks').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_flush_permalinks',
                nonce: sffcJobAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showResult(response.data.message, 'success');
                    $button.replaceWith('<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Flushed');
                } else {
                    showResult('Failed to flush permalinks', 'error');
                }
            },
            error: function() {
                showResult('Network error occurred', 'error');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Check System Status
    $('#check-status').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_check_system_status',
                nonce: sffcJobAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showResult('Status refreshed! Reloading page...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showResult('Failed to check status', 'error');
                }
            },
            error: function() {
                showResult('Network error occurred', 'error');
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Test Field Parsing
    $('#test-field-parsing').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_test_field_parsing',
                nonce: sffcJobAdmin.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    const report = response.data.report;
                    let html = '<h4>Field Parsing Test Results</h4>';
                    
                    // Summary
                    html += '<p><strong>Jobs Fetched:</strong> ' + report.jobs_fetched + '</p>';
                    html += '<p><strong>Sources:</strong> ' + report.sources.join(', ') + '</p>';
                    html += '<p><strong>Total Fields Found:</strong> ' + report.total_fields + '</p>';
                    html += '<p><strong>Well-Populated Fields (80%+):</strong> ' + report.well_populated_fields + '</p>';
                    
                    // Sample job details
                    if (report.sample_job) {
                        html += '<h5>Sample Job Analysis:</h5>';
                        html += '<ul>';
                        html += '<li>Title: ' + report.sample_job.title + '</li>';
                        html += '<li>Company: ' + report.sample_job.company + '</li>';
                        html += '<li>Location: ' + report.sample_job.location + '</li>';
                        html += '<li>Has Salary: ' + (report.sample_job.has_salary ? '✅' : '❌') + '</li>';
                        html += '<li>Has Skills: ' + (report.sample_job.has_skills ? '✅' : '❌') + '</li>';
                        html += '<li>Has Highlights: ' + (report.sample_job.has_highlights ? '✅' : '❌') + '</li>';
                        html += '<li>Quality Score: ' + report.sample_job.quality_score + '/100</li>';
                        html += '</ul>';
                    }
                    
                    // Field coverage table
                    html += '<h5>Field Coverage Details:</h5>';
                    html += '<table class="widefat" style="margin-top: 10px;">';
                    html += '<thead><tr><th>Field</th><th>Coverage</th><th>Status</th></tr></thead>';
                    html += '<tbody>';
                    
                    let count = 0;
                    for (let field in report.field_coverage) {
                        if (count++ >= 15) break; // Show top 15 fields
                        
                        const coverage = report.field_coverage[field];
                        const status = coverage.percentage >= 80 ? '✅' : 
                                      coverage.percentage >= 50 ? '⚠️' : '❌';
                        
                        html += '<tr>';
                        html += '<td><code>' + field + '</code></td>';
                        html += '<td>' + coverage.filled + '/' + coverage.total + ' (' + coverage.percentage + '%)</td>';
                        html += '<td>' + status + '</td>';
                        html += '</tr>';
                    }
                    
                    html += '</tbody></table>';
                    
                    $('#field-test-results').show();
                    $('#field-report-content').html(html);
                } else {
                    showResult('Test failed: ' + (response.data?.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showResult('Test timed out. Please try again.', 'error');
                } else {
                    showResult('Network error: ' + error, 'error');
                }
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Verify Field Saving
    $('#verify-field-saving').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        setButtonLoading($button, true);
        
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_verify_field_saving',
                nonce: sffcJobAdmin.nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    const report = response.data.report;
                    let html = '<h4>Field Saving Verification Results</h4>';
                    
                    // Test job info
                    html += '<p><strong>Test Job:</strong> ' + report.test_job.title + ' at ' + report.test_job.company + '</p>';
                    html += '<p><strong>Source:</strong> ' + report.test_job.source + '</p>';
                    html += '<p><strong>Post ID:</strong> #' + report.post_id + ' (deleted after test)</p>';
                    
                    // Save rate
                    html += '<div style="padding: 10px; background: #f0f8ff; border-left: 4px solid #2196F3; margin: 10px 0;">';
                    html += '<strong>Field Save Rate: ' + report.save_rate + '</strong>';
                    html += '</div>';
                    
                    // Saved fields
                    if (Object.keys(report.saved_fields).length > 0) {
                        html += '<h5>✅ Successfully Saved Fields:</h5>';
                        html += '<table class="widefat">';
                        html += '<thead><tr><th>Field</th><th>Value</th></tr></thead>';
                        html += '<tbody>';
                        
                        for (let field in report.saved_fields) {
                            html += '<tr>';
                            html += '<td><strong>' + field + '</strong></td>';
                            html += '<td><code>' + report.saved_fields[field] + '</code></td>';
                            html += '</tr>';
                        }
                        
                        html += '</tbody></table>';
                    }
                    
                    // Missing fields
                    if (report.missing_fields.length > 0) {
                        html += '<h5>❌ Missing Fields:</h5>';
                        html += '<ul>';
                        report.missing_fields.forEach(function(field) {
                            html += '<li>' + field + '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    // Taxonomies
                    if (Object.keys(report.taxonomies).length > 0) {
                        html += '<h5>📁 Assigned Taxonomies:</h5>';
                        html += '<ul>';
                        for (let tax in report.taxonomies) {
                            html += '<li><strong>' + tax + ':</strong> ' + report.taxonomies[tax].join(', ') + '</li>';
                        }
                        html += '</ul>';
                    }
                    
                    $('#field-test-results').show();
                    $('#field-report-content').html(html);
                } else {
                    showResult('Verification failed: ' + (response.data?.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showResult('Verification timed out. Please try again.', 'error');
                } else {
                    showResult('Network error: ' + error, 'error');
                }
            },
            complete: function() {
                setButtonLoading($button, false);
            }
        });
    });
    
    // Auto-refresh stats every 30 seconds if on the page
    let autoRefreshInterval;
    
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(function() {
            // Only refresh if no buttons are currently loading
            if ($('.sffc-action-btn.loading').length === 0) {
                updateQueueStats();
            }
        }, 30000);
    }
    
    function updateQueueStats() {
        $.ajax({
            url: sffcJobAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_check_system_status',
                nonce: sffcJobAdmin.nonce
            },
            success: function(response) {
                // Silently update stats without reloading page
                // You could update specific elements here if needed
            }
        });
    }
    
    // Start auto-refresh
    startAutoRefresh();
    
    // Stop auto-refresh when leaving page
    $(window).on('beforeunload', function() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });
    
    // Add visual feedback for all buttons
    $('.sffc-action-btn').on('click', function() {
        const $btn = $(this);
        $btn.blur(); // Remove focus after click
    });
    
    // Add tooltips
    $('.dashicons-info').on('mouseenter', function() {
        $(this).attr('title', 'Click to refresh system status');
    });
    
    // Handle any generic admin notices
    $('.notice.is-dismissible').on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
});