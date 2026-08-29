/**
 * News Feed Manager JavaScript
 * Handles AJAX interactions for the news feed management interface
 */

jQuery(document).ready(function($) {
    
    // Test single news feed
    $('.test-news-feed').on('click', function() {
        const button = $(this);
        const type = button.data('type');
        const key = button.data('key');
        
        button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_test_news_feed',
                nonce: sffcNewsFeedManager.nonce,
                type: type,
                key: key
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    if (result.success) {
                        alert(`Test successful! Found ${result.total_jobs || 'some'} jobs.`);
                        // Update status in the table
                        button.closest('tr').find('.feed-status')
                            .removeClass('status-untested status-error')
                            .addClass('status-working')
                            .text('working');
                    } else {
                        alert(`Test failed: ${result.error || 'Unknown error'}`);
                        button.closest('tr').find('.feed-status')
                            .removeClass('status-untested status-working')
                            .addClass('status-error')
                            .text('error');
                    }
                } else {
                    alert('Request failed');
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                button.prop('disabled', false).text('Test');
            }
        });
    });
    
    // Fetch news data
    $('.fetch-news-data').on('click', function() {
        const button = $(this);
        const type = button.data('type');
        const key = button.data('key');
        
        button.prop('disabled', true).text('Fetching...');
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_fetch_news_data',
                nonce: sffcNewsFeedManager.nonce,
                type: type,
                key: key
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    if (result.success) {
                        // Update job count
                        button.closest('tr').find('.job-count').text(result.jobs.length);
                        
                        // Show analysis if available
                        if (result.analysis) {
                            let analysisText = `Found ${result.jobs.length} jobs\n`;
                            analysisText += `Companies: ${Object.keys(result.analysis.companies).length}\n`;
                            analysisText += `Top companies: ${Object.keys(result.analysis.companies).slice(0, 3).join(', ')}`;
                            alert(analysisText);
                        } else {
                            alert(`Successfully fetched ${result.jobs.length} jobs for analysis`);
                        }
                    } else {
                        alert('No jobs found');
                    }
                } else {
                    alert('Request failed');
                }
            },
            error: function() {
                alert('Connection error');
            },
            complete: function() {
                button.prop('disabled', false).text('Fetch Data');
            }
        });
    });
    
    // Generate weekly news
    $('#sffc-generate-weekly-news').on('click', function() {
        const button = $(this);
        const statusDiv = $('#sffc-news-generation-status');
        const progressBar = statusDiv.find('.sffc-progress-fill');
        const statusMessage = statusDiv.find('.sffc-status-message');
        
        button.prop('disabled', true);
        statusDiv.show();
        statusMessage.text('Initializing news generation...');
        progressBar.css('width', '10%');
        
        // Simulate progress updates
        let progress = 10;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 20;
            if (progress > 85) progress = 85; // Cap at 85% until complete
            progressBar.css('width', progress + '%');
            
            if (progress < 30) {
                statusMessage.text('Fetching job data from feeds...');
            } else if (progress < 50) {
                statusMessage.text('Analyzing hiring trends...');
            } else if (progress < 70) {
                statusMessage.text('Generating insights with Claude AI...');
            } else {
                statusMessage.text('Creating news article...');
            }
        }, 1000);
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_generate_weekly_news',
                nonce: sffcNewsFeedManager.nonce
            },
            timeout: 600000, // 10 minutes
            success: function(response) {
                clearInterval(progressInterval);
                progressBar.css('width', '100%');
                
                if (response.success) {
                    const result = response.data;
                    statusMessage.html(`
                        <strong>✅ News article generated successfully!</strong><br>
                        Post ID: ${result.post_id}<br>
                        Jobs analyzed: ${result.analysis.total_jobs}<br>
                        Companies tracked: ${Object.keys(result.analysis.companies || {}).length}<br>
                        <a href="/wp-admin/post.php?post=${result.post_id}&action=edit" target="_blank">Edit Article</a>
                    `);
                    
                    // Show preview
                    if (result.article_preview) {
                        statusMessage.append(`<br><br><strong>Preview:</strong><br>${result.article_preview}...`);
                    }
                } else {
                    statusMessage.html(`<span style="color: #d63638;">❌ Generation failed: ${result.message || 'Unknown error'}</span>`);
                }
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                progressBar.css('width', '100%');
                statusMessage.html(`<span style="color: #d63638;">❌ Error: ${error || 'Connection failed'}</span>`);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
    
    // Add new workday feed
    $('#add-news-workday-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_add_news_workday_feed&nonce=' + sffcNewsFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    if (confirm('News Workday feed added successfully! Reload page to see changes?')) {
                        window.location.reload();
                    }
                } else {
                    alert('Failed to add feed: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { xhr, status, error });
                alert('Connection error: ' + error);
            }
        });
    });
    
    // Add new XML feed
    $('#add-news-xml-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: formData + '&action=sffc_add_news_xml_feed&nonce=' + sffcNewsFeedManager.nonce,
            success: function(response) {
                if (response.success) {
                    if (confirm('News XML feed added successfully! Reload page to see changes?')) {
                        window.location.reload();
                    }
                } else {
                    alert('Failed to add feed: ' + (response.data?.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', { xhr, status, error });
                alert('Connection error: ' + error);
            }
        });
    });
    
    // Remove feed
    $('.remove-news-feed').on('click', function() {
        if (!confirm('Are you sure you want to remove this news feed?')) {
            return;
        }
        
        const button = $(this);
        const type = button.data('type');
        const key = button.data('key');
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_remove_news_feed',
                nonce: sffcNewsFeedManager.nonce,
                type: type,
                key: key
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut();
                } else {
                    alert('Failed to remove feed');
                }
            },
            error: function() {
                alert('Connection error');
            }
        });
    });
    
    // Select all checkboxes
    $('#select-all-news-workday').on('change', function() {
        $('#news-workday-feeds-list input[type="checkbox"]').prop('checked', this.checked);
    });
    
    $('#select-all-news-xml').on('change', function() {
        $('#news-xml-feeds-list input[type="checkbox"]').prop('checked', this.checked);
    });
    
    // Bulk operations
    $('#apply-news-bulk-action').on('click', function() {
        const action = $('#news-bulk-action').val();
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        const selectedFeeds = $('.news-feed-checkbox:checked').map(function() {
            return $(this).data('type') + '_' + $(this).data('key');
        }).get();
        
        if (selectedFeeds.length === 0) {
            alert('Please select at least one feed');
            return;
        }
        
        if (!confirm(`Apply "${action}" to ${selectedFeeds.length} selected feeds?`)) {
            return;
        }
        
        const statusSpan = $('#news-bulk-status');
        statusSpan.text('Processing...');
        
        $.ajax({
            url: sffcNewsFeedManager.ajaxUrl,
            type: 'POST',
            data: {
                action: 'sffc_news_bulk_operation',
                nonce: sffcNewsFeedManager.nonce,
                action_type: action,
                feeds: selectedFeeds
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    statusSpan.text(`Completed: ${result.success} successful, ${result.failed} failed`);
                    
                    if (action === 'remove') {
                        // Remove rows for successfully removed feeds
                        $('.news-feed-checkbox:checked').each(function() {
                            $(this).closest('tr').fadeOut();
                        });
                    }
                } else {
                    statusSpan.text('Operation failed');
                }
            },
            error: function() {
                statusSpan.text('Connection error');
            }
        });
    });
    
    // Edit feed functionality (placeholder)
    $('.edit-news-feed').on('click', function() {
        alert('Edit functionality coming soon! Please remove and re-add the feed for now.');
    });
});