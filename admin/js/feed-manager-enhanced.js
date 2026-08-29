/**
 * Enhanced Feed Manager JavaScript
 * Includes edit, remove, and bulk operations
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Select all checkboxes
    $('#select-all-workday').on('change', function() {
        $('.feed-checkbox[data-type="workday"]').prop('checked', $(this).prop('checked'));
    });
    
    $('#select-all-xml').on('change', function() {
        $('#xml-feeds-list .feed-checkbox[data-type="xml"]').prop('checked', $(this).prop('checked'));
    });

    $('#select-all-aggregators').on('change', function() {
        $('#aggregator-feeds-list .feed-checkbox[data-type="xml"]').prop('checked', $(this).prop('checked'));
    });

    ensureProgressiveFetchControls();
    
    // Edit feed
    $(document).on('click', '.edit-feed', function() {
        const $button = $(this);
        const type = $button.data('type');
        const key = $button.data('key');
        
        // Get feed data
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_get_feed_data',
                type: type,
                key: key,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    const feed = response.data;
                    
                    // Populate modal
                    $('#edit-feed-type').val(type);
                    $('#edit-feed-original-key').val(key);
                    $('#edit-feed-key').val(key);
                    $('#edit-feed-name').val(feed.company_name || feed.name || '');
                    $('#edit-status').val(feed.status || 'untested');
                    
                    if (type === 'workday') {
                        $('#edit-base-url').val(feed.base_url || '');
                        $('#edit-endpoint').val(feed.endpoint || '');
                        $('#edit-careers-path').val(feed.careers_path || '');
                        $('.workday-fields').show();
                        $('.xml-fields').hide();
                    } else {
                        $('#edit-feed-url').val(feed.url || '');
                        $('#edit-xml-type').val(feed.type || 'sitemap');
                        $('#edit-source-type').val(feed.source_type || 'company');
                        $('.workday-fields').hide();
                        $('.xml-fields').show();
                    }
                    
                    $('#edit-feed-modal').fadeIn();
                }
            }
        });
    });
    
    // Close modal
    $('.close-modal, .cancel-edit').on('click', function() {
        $('#edit-feed-modal').fadeOut();
    });
    
    // Save edit
    $('#edit-feed-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_edit_feed&nonce=' + sffcFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    showResult('Feed updated successfully!', 'success');
                    $('#edit-feed-modal').fadeOut();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showResult('Failed to update feed', 'error');
                }
            },
            error: function() {
                showResult('Network error', 'error');
            }
        });
    });
    
    // Enhanced remove with confirmation
    $(document).on('click', '.remove-feed', function() {
        const $button = $(this);
        const type = $button.data('type');
        const key = $button.data('key');
        const $row = $button.closest('tr');
        const feedName = $row.find('td:nth-child(2) strong').text();
        
        if (!confirm('Remove feed "' + feedName + '"?\n\nNote: Default feeds will be disabled, custom feeds will be permanently deleted.')) {
            return;
        }
        
        $button.prop('disabled', true).text('Removing...');
        
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
                    $row.fadeOut(function() {
                        $(this).remove();
                    });
                    showResult('Feed removed successfully', 'success');
                } else {
                    showResult('Failed to remove feed', 'error');
                    $button.prop('disabled', false).text('Remove');
                }
            },
            error: function() {
                showResult('Network error', 'error');
                $button.prop('disabled', false).text('Remove');
            }
        });
    });
    
    // Bulk operations
    $('#apply-bulk-action').on('click', function() {
        const action = $('#bulk-action').val();
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        const selectedFeeds = [];
        $('.feed-checkbox:checked').each(function() {
            const type = $(this).data('type');
            const key = $(this).data('key');
            selectedFeeds.push(type + '_' + key);
        });
        
        if (selectedFeeds.length === 0) {
            alert('Please select at least one feed');
            return;
        }
        
        let confirmMsg = 'Apply "' + action + '" to ' + selectedFeeds.length + ' selected feeds?';
        if (action === 'remove') {
            confirmMsg += '\n\nThis action cannot be undone for custom feeds!';
        }
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        const $button = $(this);
        const $status = $('#bulk-status');
        
        $button.prop('disabled', true);
        $status.text('Processing...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_bulk_operation',
                action_type: action,
                feeds: selectedFeeds,
                nonce: sffcFeedManager.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    $status.text('Completed: ' + data.success + ' succeeded, ' + data.failed + ' failed');
                    
                    if (action === 'remove' || action === 'disable') {
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                } else {
                    $status.text('Operation failed');
                }
            },
            error: function() {
                $status.text('Network error');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });

    const progressiveFetchRuns = new WeakMap();

    function ensureProgressiveFetchControls() {
        [
            {
                target: '#workday-feeds-list',
                label: 'Workday API Feeds',
                button: 'Fetch All Workday Feeds'
            },
            {
                target: '#xml-feeds-list',
                label: 'XML / ATS Feeds',
                button: 'Fetch All XML / ATS Feeds'
            },
            {
                target: '#aggregator-feeds-list',
                label: 'Job Aggregators',
                button: 'Fetch All Job Aggregators'
            }
        ].forEach(function(config) {
            const $list = $(config.target);
            const $table = $list.closest('table');
            const hasServerControl = $('.sffc-progressive-fetch').filter(function() {
                return $(this).data('feed-target') === config.target;
            }).length > 0;

            if (!$list.length || !$table.length || hasServerControl) {
                return;
            }

            $table.before(buildProgressiveFetchControl(config));
        });
    }

    function buildProgressiveFetchControl(config) {
        const $control = $('<div/>', {
            class: 'sffc-progressive-fetch',
            'data-feed-target': config.target,
            'data-feed-label': config.label
        });

        $('<button/>', {
            type: 'button',
            class: 'button button-primary sffc-fetch-section-feeds',
            text: config.button
        }).appendTo($control);

        $('<button/>', {
            type: 'button',
            class: 'button sffc-stop-section-fetch',
            text: 'Stop',
            disabled: true
        }).appendTo($control);

        $('<div/>', {
            class: 'sffc-progressive-fetch__status',
            'aria-live': 'polite',
            text: 'Ready to fetch this section.'
        }).appendTo($control);

        $('<div/>', {
            class: 'sffc-progressive-fetch__bar',
            role: 'progressbar',
            'aria-valuemin': '0',
            'aria-valuemax': '100',
            'aria-valuenow': '0'
        }).append($('<span/>')).appendTo($control);

        return $control;
    }

    $(document).on('click', '.sffc-fetch-section-feeds', function() {
        const $control = $(this).closest('.sffc-progressive-fetch');
        const target = $control.data('feed-target');
        const label = $control.data('feed-label') || 'feeds';
        const $rows = $(target).find('tr').filter(function() {
            return $(this).find('.feed-status').text().trim().toLowerCase() !== 'disabled';
        });
        const $fetchButton = $control.find('.sffc-fetch-section-feeds');

        if ($rows.length === 0) {
            updateProgressiveFetchStatus($control, 'No enabled feeds found in ' + label + '.', 0);
            return;
        }

        if (!confirm('Fetch and queue roles from all ' + $rows.length + ' enabled ' + label + '?')) {
            return;
        }

        const run = {
            stopped: false,
            total: $rows.length,
            processed: 0,
            succeeded: 0,
            failed: 0,
            fetched: 0,
            eligible: 0,
            saved: 0,
            skipped: 0,
            schemasDiscovered: 0,
            schemasCached: 0,
            schemasFailed: 0,
            originalButtonText: $fetchButton.text()
        };

        progressiveFetchRuns.set($control[0], run);
        $control.addClass('is-running');
        $fetchButton.prop('disabled', true).text('Fetching...');
        $control.find('.sffc-stop-section-fetch').prop('disabled', false);
        updateProgressiveFetchStatus($control, 'Starting ' + label + '...', 0);

        fetchSectionFeedAtIndex($control, $rows, 0, run, label);
    });

    $(document).on('click', '.sffc-stop-section-fetch', function() {
        const $control = $(this).closest('.sffc-progressive-fetch');
        const run = progressiveFetchRuns.get($control[0]);
        if (run) {
            run.stopped = true;
            updateProgressiveFetchStatus($control, 'Stopping after the current feed finishes...', calculateProgress(run));
        }
        $(this).prop('disabled', true);
    });

    function fetchSectionFeedAtIndex($control, $rows, index, run, label) {
        if (run.stopped || index >= $rows.length) {
            finishProgressiveFetch($control, run, label);
            return;
        }

        const $row = $($rows[index]);
        const $checkbox = $row.find('.feed-checkbox').first();
        const type = $checkbox.data('type');
        const key = $checkbox.data('key');
        const feedName = $row.find('td:nth-child(2) strong').text() || key;

        $row.removeClass('sffc-feed-row-success sffc-feed-row-error').addClass('sffc-feed-row-running');
        $row.find('.job-count').text('...');
        updateProgressiveFetchStatus(
            $control,
            'Fetching ' + (index + 1) + ' of ' + run.total + ': ' + feedName,
            calculateProgress(run)
        );

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
            timeout: 300000,
            success: function(response) {
                run.processed++;
                $row.removeClass('sffc-feed-row-running');

                if (response.success && response.data && response.data.success) {
                    const jobs = Array.isArray(response.data.jobs) ? response.data.jobs : [];
                    const fetched = jobs.length;
                    const eligible = parseInt(response.data.eligible || 0, 10);
                    const saved = parseInt(response.data.saved || 0, 10);
                    const updated = parseInt(response.data.updated || 0, 10);
                    const skipped = parseInt(response.data.skipped || 0, 10);
                    const schemasDiscovered = parseInt(response.data.schemas_discovered || 0, 10);
                    const schemasCached = parseInt(response.data.schemas_cached || 0, 10);
                    const schemasFailed = parseInt(response.data.schemas_failed || 0, 10);

                    run.succeeded++;
                    run.fetched += fetched;
                    run.eligible += eligible;
                    run.saved += saved + updated;
                    run.skipped += skipped;
                    run.schemasDiscovered += schemasDiscovered;
                    run.schemasCached += schemasCached;
                    run.schemasFailed += schemasFailed;

                    $row.addClass('sffc-feed-row-success');
                    $row.find('.job-count').text(fetched);
                    $row.find('.feed-status')
                        .removeClass('status-untested status-error status-disabled')
                        .addClass('status-working')
                        .text('working');
                } else {
                    run.failed++;
                    $row.addClass('sffc-feed-row-error');
                    $row.find('.job-count').text('failed');
                    $row.find('.feed-status')
                        .removeClass('status-untested status-working status-disabled')
                        .addClass('status-error')
                        .text('error');
                }
            },
            error: function() {
                run.processed++;
                run.failed++;
                $row.removeClass('sffc-feed-row-running').addClass('sffc-feed-row-error');
                $row.find('.job-count').text('failed');
                $row.find('.feed-status')
                    .removeClass('status-untested status-working status-disabled')
                    .addClass('status-error')
                    .text('error');
            },
            complete: function() {
                updateProgressiveFetchStatus($control, buildProgressiveFetchSummary(run, label), calculateProgress(run));
                fetchSectionFeedAtIndex($control, $rows, index + 1, run, label);
            }
        });
    }

    function finishProgressiveFetch($control, run, label) {
        const stoppedText = run.stopped ? 'Stopped. ' : 'Completed. ';
        $control.removeClass('is-running');
        $control.find('.sffc-fetch-section-feeds').prop('disabled', false).text(run.originalButtonText || ('Fetch All ' + label));
        $control.find('.sffc-stop-section-fetch').prop('disabled', true);
        updateProgressiveFetchStatus($control, stoppedText + buildProgressiveFetchSummary(run, label), calculateProgress(run));
    }

    function buildProgressiveFetchSummary(run, label) {
        return run.processed + '/' + run.total + ' ' + label + ' processed. ' +
            run.succeeded + ' succeeded, ' +
            run.failed + ' failed, ' +
            run.fetched + ' fetched, ' +
            run.eligible + ' eligible, ' +
            run.saved + ' draft(s) queued/updated, ' +
            run.skipped + ' skipped, ' +
            run.schemasDiscovered + ' schema(s) discovered, ' +
            run.schemasCached + ' schema(s) cached' +
            (run.schemasFailed ? ', ' + run.schemasFailed + ' schema fetch failed' : '') +
            '.';
    }

    function calculateProgress(run) {
        if (!run || !run.total) {
            return 0;
        }

        return Math.round((run.processed / run.total) * 100);
    }

    function updateProgressiveFetchStatus($control, message, percent) {
        const boundedPercent = Math.max(0, Math.min(100, parseInt(percent || 0, 10)));
        $control.find('.sffc-progressive-fetch__status').text(message);
        $control.find('.sffc-progressive-fetch__bar')
            .attr('aria-valuenow', boundedPercent)
            .find('span')
            .css('width', boundedPercent + '%');
    }
    
    // Enhanced add Workday feed with validation
    $('#add-workday-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const baseUrl = $form.find('[name="base_url"]').val();
        
        // Validate Workday URL format
        if (!baseUrl.includes('.myworkdayjobs.com')) {
            if (!confirm('This doesn\'t look like a standard Workday URL.\nTypical format: https://company.wd1.myworkdayjobs.com\n\nContinue anyway?')) {
                return;
            }
        }
        
        const formData = $form.serialize();
        const $button = $form.find('button[type="submit"]');
        
        $button.prop('disabled', true).text('Adding & Testing...');
        
        $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_add_workday_feed&nonce=' + sffcFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    showResult('Workday feed added successfully!', 'success');
                    
                    // Auto-test the new feed
                    const companyKey = $form.find('[name="company_key"]').val();
                    testFeedAfterAdd('workday', companyKey);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    showResult('Failed to add feed: ' + (response.data?.message || 'Unknown error'), 'error');
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
    
    // Auto-test feed after adding
    function testFeedAfterAdd(type, key) {
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
                    showResult('✅ Feed test successful! Found ' + response.data.total_jobs + ' jobs', 'success');
                } else {
                    showResult('⚠️ Feed added but test failed. Check configuration.', 'warning');
                }
            }
        });
    }
    
    // Test single feed (existing)
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
                        .removeClass('status-untested status-error status-disabled')
                        .addClass('status-working')
                        .text('working');
                    
                    showResult('Feed is working! Found ' + jobs + ' jobs.', 'success');
                } else {
                    $row.find('.feed-status')
                        .removeClass('status-untested status-working status-disabled')
                        .addClass('status-error')
                        .text('error');
                    
                    const error = response.data?.error || 'Unknown error';
                    showResult('Feed test failed: ' + error, 'error');
                    
                    // If 404, suggest using auto-detection
                    if (error.includes('404') || error.includes('not found')) {
                        if (confirm('Endpoint not found. Try auto-detecting the correct endpoint?')) {
                            // Could trigger auto-detection here
                            showResult('Auto-detection feature coming soon!', 'info');
                        }
                    }
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
    
    // Fetch jobs from feed (existing)
    $('.fetch-jobs').on('click', function() {
        const $button = $(this);
        const type = $button.data('type');
        const key = $button.data('key');
        
        const fetchLimit = parseInt(sffcFeedManager.fetchLimit || 50, 10);

        if (!confirm('Fetch and queue up to ' + fetchLimit + ' investment and finance roles from this feed for editorial review?')) {
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
            timeout: 300000, // 5 minutes timeout
            success: function(response) {
                if (response.success && response.data.success) {
                    const fetched = response.data.jobs.length;
                    const saved = response.data.saved;
                    const skipped = response.data.skipped || 0;
                    const eligible = response.data.eligible || 0;
                    const schemasDiscovered = response.data.schemas_discovered || 0;
                    const schemasCached = response.data.schemas_cached || 0;
                    const schemasFailed = response.data.schemas_failed || 0;
                    const schemaSummary = schemasDiscovered || schemasCached || schemasFailed
                        ? ' Schema: ' + schemasDiscovered + ' discovered, ' + schemasCached + ' cached' + (schemasFailed ? ', ' + schemasFailed + ' failed.' : '.')
                        : '';

                    showResult('Fetched ' + fetched + ' roles, ' + eligible + ' eligible, ' + saved + ' draft(s) queued, ' + skipped + ' skipped.' + schemaSummary, 'success');
                    
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

    // Helper function to show results
    function showResult(message, type) {
        const alertClass = type === 'success' ? 'notice-success' : 
                          type === 'warning' ? 'notice-warning' : 'notice-error';
        const $alert = $('<div class="notice ' + alertClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($alert);
        
        setTimeout(function() {
            $alert.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Add XML feed (existing code)
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
    
    // Title Cleanup Functionality
    let currentPreviewData = [];
    let currentOffset = 0;
    
    // WordPress-style button handling
    $(document).on('click', '.cleanup-preview-btn', function(e) {
        e.preventDefault();
        console.log('Preview button clicked');
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Loading...');
        
        previewTitles().always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Preview Changes');
        });
    });
    
    $(document).on('click', '.cleanup-start-btn', function(e) {
        e.preventDefault();
        console.log('Start cleanup button clicked');
        
        const $btn = $(this);
        const previewMode = $('#cleanup-preview-mode').is(':checked');
        
        $btn.prop('disabled', true);
        
        if (previewMode) {
            previewTitles().always(function() {
                $btn.prop('disabled', false);
            });
        } else {
            startBatchCleanup();
            $btn.prop('disabled', false);
        }
    });
    
    // Apply changes button - using event delegation
    $(document).on('click', '#apply-title-changes', function() {
        console.log('Apply changes button clicked');
        applyTitleChanges();
    });
    
    // Cancel preview button - using event delegation
    $(document).on('click', '#cancel-preview', function() {
        console.log('Cancel preview button clicked');
        cancelPreview();
    });
    
    // Reset button - using event delegation
    $(document).on('click', '#reset-cleanup', function() {
        console.log('Reset button clicked');
        resetCleanup();
    });
    
    // Preview pagination - using event delegation
    $(document).on('click', '#preview-prev', function() {
        console.log('Previous button clicked');
        if (currentOffset > 0) {
            loadPreviewPage(currentOffset - parseInt($('#cleanup-batch-size').val()));
        }
    });
    
    $(document).on('click', '#preview-next', function() {
        console.log('Next button clicked');
        const batchSize = parseInt($('#cleanup-batch-size').val());
        loadPreviewPage(currentOffset + batchSize);
    });
    
    function previewTitles() {
        const sourceFilter = $('#cleanup-source-filter').val() || 'all';
        const countryFilter = $('#cleanup-country-filter').val() || 'global';
        const batchSize = parseInt($('#cleanup-batch-size').val()) || 50;
        
        console.log('Starting preview with:', sourceFilter, countryFilter, batchSize);
        showProgress('Loading job titles for preview...', 10);
        
        return $.ajax({
            url: sffcFeedManager.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sffc_preview_title_cleanup',
                source_filter: sourceFilter,
                country_filter: countryFilter,
                batch_size: batchSize,
                offset: 0,
                nonce: sffcFeedManager.nonce
            }
        }).done(function(response) {
            console.log('Preview response:', response);
            hideProgress();
            
            if (response && response.success && response.data) {
                currentPreviewData = response.data.preview_data || [];
                currentOffset = 0;
                displayPreview(response.data);
                showResult(`Loaded ${currentPreviewData.length} jobs for preview`, 'success');
            } else {
                const errorMsg = response && response.data ? response.data : 'Unknown error';
                showResult('Preview failed: ' + errorMsg, 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
            hideProgress();
            showResult('Network error while loading preview: ' + error, 'error');
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
        return String(unsafe || '')
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});
