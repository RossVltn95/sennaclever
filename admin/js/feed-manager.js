/**
 * Feed Manager JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Fetch manual feeds
    $('#fetch-manual-feeds').on('click', function() {
        const $button = $(this);
        const $result = $('#manual-feeds-result');
        
        $button.prop('disabled', true);
        $button.find('span').removeClass('dashicons-update').addClass('dashicons-update-alt');
        $button.append('<span class="spinner is-active" style="margin: 0 0 0 5px;"></span>');
        
        $result.html('<div class="notice notice-info"><p>Processing manual feeds...</p></div>');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_fetch_manual_feeds',
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Network error while processing manual feeds</p></div>');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.find('span').first().removeClass('dashicons-update-alt').addClass('dashicons-update');
                $button.find('.spinner').remove();
            }
        });
    });
    
    // Test single feed
    $('.test-feed').on('click', function() {
        const $button = $(this);
        const $row = $button.closest('tr');
        const type = $button.data('type');
        const key = $button.data('key');
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_test_single_feed',
                type: type,
                key: key,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success && response.data.success) {
                    const jobs = response.data.total_jobs || 0;
                    $row.find('.job-count').text(jobs);
                    $row.find('.feed-status')
                        .removeClass('status-untested status-error')
                        .addClass('status-working')
                        .text('working');
                    
                    showResult('Feed is working! Found ' + jobs + ' jobs.', 'success');
                } else {
                    $row.find('.feed-status')
                        .removeClass('status-untested status-working')
                        .addClass('status-error')
                        .text('error');
                    
                    showResult('Feed test failed: ' + (response.data.error || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showResult('Network error while testing feed', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test');
            }
        });
    });
    
    // Fetch jobs from feed
    $('.fetch-jobs').on('click', function() {
        const $button = $(this);
        const type = $button.data('type');
        const key = $button.data('key');
        
        const fetchLimit = parseInt(sffcFeedManager.fetchLimit || 50, 10);

        if (!confirm('Fetch and queue up to ' + fetchLimit + ' finance roles from this feed for editorial review?')) {
            return;
        }
        
        $button.prop('disabled', true).text('Fetching...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_fetch_from_feed',
                type: type,
                key: key,
                save: 'true',
                nonce: sffcFeedManager.nonce
            },
            timeout: 120000, // 2 minutes timeout
            success: function(response) {
                if (response.success && response.data.success) {
                    const fetched = response.data.jobs.length;
                    const saved = response.data.saved;
                    const skipped = response.data.skipped || 0;
                    const eligible = response.data.eligible || 0;

                    showResult('Fetched ' + fetched + ' roles, ' + eligible + ' eligible, ' + saved + ' draft(s) queued, ' + skipped + ' skipped.', 'success');
                    
                    // Display job details
                    if (response.data.jobs.length > 0) {
                        let html = '<h4>Fetched Jobs:</h4><ul>';
                        response.data.jobs.forEach(function(job) {
                            html += '<li><strong>' + job.title + '</strong> at ' + job.company + ' (' + job.location + ')</li>';
                        });
                        html += '</ul>';
                        $('#test-output').html(html);
                        $('#feed-test-results').show();
                    }
                } else {
                    showResult('Failed to fetch roles from this feed: ' + (response.data.error || 'Unknown error'), 'error');
                }
            },
            error: function() {
                showResult('Request timed out or network error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Fetch ' + fetchLimit + ' Roles');
            }
        });
    });
    
    // Add Workday feed
    $('#add-workday-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const $button = $(this).find('button[type="submit"]');
        
        $button.prop('disabled', true).text('Adding...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_add_workday_feed&nonce=' + sffcFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    showResult('Workday feed added successfully! Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showResult('Failed to add feed', 'error');
                }
            },
            error: function() {
                showResult('Network error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Add Workday Feed');
            }
        });
    });
    
    // Add XML feed
    $('#add-xml-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const $button = $(this).find('button[type="submit"]');
        
        $button.prop('disabled', true).text('Adding...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_add_xml_feed&nonce=' + sffcFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    showResult('XML feed added successfully! Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showResult('Failed to add feed', 'error');
                }
            },
            error: function() {
                showResult('Network error', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Add XML Feed');
            }
        });
    });
    
    // Remove feed
    $(document).on('click', '.remove-feed', function() {
        const $button = $(this);
        const type = $button.data('type');
        const key = $button.data('key');
        
        if (!confirm('Remove this custom feed?')) {
            return;
        }
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_remove_feed',
                type: type,
                key: key,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                    showResult('Feed removed', 'success');
                }
            }
        });
    });
    
    // Title Cleanup Functionality
    let currentPreviewData = [];
    let currentOffset = 0;
    
    // Preview titles button
    $('#preview-titles').on('click', function() {
        previewTitles();
    });
    
    // Start cleanup button
    $('#start-title-cleanup').on('click', function() {
        const previewMode = $('#cleanup-preview-mode').is(':checked');
        
        if (previewMode) {
            previewTitles();
        } else {
            startBatchCleanup();
        }
    });
    
    // Apply changes button
    $('#apply-title-changes').on('click', function() {
        applyTitleChanges();
    });
    
    // Cancel preview button
    $('#cancel-preview').on('click', function() {
        cancelPreview();
    });
    
    // Reset button
    $('#reset-cleanup').on('click', function() {
        resetCleanup();
    });
    
    // Preview pagination
    $('#preview-prev').on('click', function() {
        if (currentOffset > 0) {
            loadPreviewPage(currentOffset - parseInt($('#cleanup-batch-size').val()));
        }
    });
    
    $('#preview-next').on('click', function() {
        const batchSize = parseInt($('#cleanup-batch-size').val());
        loadPreviewPage(currentOffset + batchSize);
    });
    
    function previewTitles() {
        const sourceFilter = $('#cleanup-source-filter').val();
        const countryFilter = $('#cleanup-country-filter').val();
        const batchSize = parseInt($('#cleanup-batch-size').val());
        
        showProgress('Loading job titles for preview...', 0);
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_preview_title_cleanup',
                source_filter: sourceFilter,
                country_filter: countryFilter,
                batch_size: batchSize,
                offset: 0,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                hideProgress();
                
                if (response.success) {
                    currentPreviewData = response.data.preview_data;
                    currentOffset = 0;
                    displayPreview(response.data);
                } else {
                    showResult('Preview failed: ' + response.data, 'error');
                }
            },
            error: function() {
                hideProgress();
                showResult('Network error while loading preview', 'error');
            }
        });
    }
    
    function displayPreview(data) {
        $('#title-preview').show();
        
        const $tableBody = $('#preview-table-body');
        $tableBody.empty();
        
        data.preview_data.forEach(function(job) {
            const hasChanged = job.original_title !== job.standardized_title;
            const rowClass = hasChanged ? 'title-change' : 'title-unchanged';
            
            const $row = $(`
                <tr class="${rowClass}" data-job-id="${job.id}">
                    <td>${job.id}</td>
                    <td class="original-title">${escapeHtml(job.original_title)}</td>
                    <td class="standardized-title">${escapeHtml(job.standardized_title)}</td>
                    <td>${escapeHtml(job.location)}</td>
                    <td>${escapeHtml(job.source)}</td>
                </tr>
            `);
            
            $tableBody.append($row);
        });
        
        updatePreviewPagination(data);
    }
    
    function updatePreviewPagination(data) {
        const totalPages = Math.ceil(data.total_jobs / data.batch_size);
        const currentPage = Math.floor(data.current_offset / data.batch_size) + 1;
        
        $('#preview-page-info').text(`Page ${currentPage} of ${totalPages} (${data.total_jobs} total jobs)`);
        
        $('#preview-prev').prop('disabled', data.current_offset === 0);
        $('#preview-next').prop('disabled', !data.has_more);
    }
    
    function loadPreviewPage(offset) {
        const sourceFilter = $('#cleanup-source-filter').val();
        const countryFilter = $('#cleanup-country-filter').val();
        const batchSize = parseInt($('#cleanup-batch-size').val());
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_preview_title_cleanup',
                source_filter: sourceFilter,
                country_filter: countryFilter,
                batch_size: batchSize,
                offset: offset,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentPreviewData = response.data.preview_data;
                    currentOffset = offset;
                    displayPreview(response.data);
                }
            }
        });
    }
    
    function applyTitleChanges() {
        const jobIds = [];
        const standardizedTitles = [];
        
        $('#preview-table-body tr').each(function() {
            const $row = $(this);
            if ($row.hasClass('title-change')) {
                jobIds.push(parseInt($row.data('job-id')));
                standardizedTitles.push($row.find('.standardized-title').text());
            }
        });
        
        if (jobIds.length === 0) {
            showResult('No changes to apply', 'warning');
            return;
        }
        
        showProgress(`Applying changes to ${jobIds.length} job titles...`, 0);
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_apply_title_cleanup',
                job_ids: jobIds,
                standardized_titles: standardizedTitles,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                hideProgress();
                
                if (response.success) {
                    $('#title-preview').hide();
                    displayResults(response.data);
                    showResult(response.data.message, 'success');
                } else {
                    showResult('Apply failed: ' + response.data, 'error');
                }
            },
            error: function() {
                hideProgress();
                showResult('Network error while applying changes', 'error');
            }
        });
    }
    
    function startBatchCleanup() {
        showResult('Batch cleanup without preview is not yet implemented. Please use preview mode.', 'warning');
    }
    
    function displayResults(data) {
        const $summary = $('#cleanup-summary');
        
        $summary.html(`
            <div class="cleanup-stat">
                <span class="stat-number">${data.updated_count}</span>
                <span class="stat-label">job titles updated successfully</span>
            </div>
            <div class="cleanup-stat">
                <span class="stat-number">${data.total_jobs}</span>
                <span class="stat-label">total jobs processed</span>
            </div>
            ${data.failed_updates.length > 0 ? `
            <div class="cleanup-stat">
                <span class="stat-number">${data.failed_updates.length}</span>
                <span class="stat-label">updates failed</span>
            </div>
            <div style="margin-top: 15px;">
                <strong>Failed Updates:</strong>
                <ul>${data.failed_updates.map(error => `<li>${escapeHtml(error)}</li>`).join('')}</ul>
            </div>
            ` : ''}
        `);
        
        $('#cleanup-results').show();
        $('#reset-cleanup').show();
    }
    
    function cancelPreview() {
        $('#title-preview').hide();
        currentPreviewData = [];
        currentOffset = 0;
    }
    
    function resetCleanup() {
        $('#cleanup-progress').hide();
        $('#title-preview').hide();
        $('#cleanup-results').hide();
        $('#reset-cleanup').hide();
        currentPreviewData = [];
        currentOffset = 0;
        updateProgress(0, 'Ready to start cleanup');
    }
    
    function showProgress(message, percent) {
        $('#cleanup-progress').show();
        updateProgress(percent, message);
    }
    
    function hideProgress() {
        $('#cleanup-progress').hide();
    }
    
    function updateProgress(percent, message) {
        $('#progress-fill').css('width', percent + '%');
        $('#progress-text').text(message);
        
        if (percent > 0) {
            $('#progress-stats').text(`${percent}% complete`);
        } else {
            $('#progress-stats').text('');
        }
    }
    
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    // Helper function to show results
    function showResult(message, type) {
        const alertClass = type === 'success' ? 'notice-success' : (type === 'warning' ? 'notice-warning' : 'notice-error');
        const $alert = $('<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($alert);
        
        setTimeout(function() {
            $alert.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
});
