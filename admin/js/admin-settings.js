/**
 * Admin Settings JavaScript for Enhanced Claude Configuration
 */

jQuery(document).ready(function($) {
    
    // WordPress provides ajaxurl globally in admin, but let's ensure it exists
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '/wp-admin/admin-ajax.php';
    }
    
    // Get the nonce from the settings form or localized script
    const ajax_nonce = typeof sffc_settings !== 'undefined' ? sffc_settings.nonce : 
                      (typeof sffc_admin !== 'undefined' ? sffc_admin.nonce : 
                      ($('input[name="_wpnonce"]').val() || ''));
    const feedManagementEnabled = typeof sffc_settings === 'undefined' || sffc_settings.pe_news_feed_enabled !== false;
    
    // Temperature Range Slider
    $('#sffc_claude_temperature').on('input', function() {
        $('.sffc-range-value').text($(this).val());
    });
    
    // API Key Visibility Toggle
    $('#sffc_api_key').after('<button type="button" class="button sffc-toggle-api-key" style="margin-left: 10px;">Show</button>');
    
    $('.sffc-toggle-api-key').click(function(e) {
        e.preventDefault();
        const $apiKeyInput = $('#sffc_api_key');
        const $button = $(this);
        
        if ($apiKeyInput.attr('type') === 'password') {
            $apiKeyInput.attr('type', 'text');
            $button.text('Hide');
        } else {
            $apiKeyInput.attr('type', 'password');
            $button.text('Show');
        }
    });
    
    // Test API Connection
    if ($('#sffc_api_key').val()) {
        $('#sffc_api_key').after('<button type="button" class="button sffc-test-api" style="margin-left: 10px;">Test Connection</button>');
        
        $('.sffc-test-api').click(function(e) {
            e.preventDefault();
            const $button = $(this);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Testing...');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'sffc_test_claude_api',
                    nonce: $('input[name="_wpnonce"]').val(),
                    api_key: $('#sffc_api_key').val(),
                    model: $('#sffc_claude_model').val(),
                    temperature: $('#sffc_claude_temperature').val(),
                    max_tokens: $('#sffc_claude_max_tokens').val()
                },
                success: function(response) {
                    if (response.success) {
                        $button.after('<span class="sffc-test-result success" style="margin-left: 10px; color: #28a745;">✓ Connected</span>');
                    } else {
                        $button.after('<span class="sffc-test-result error" style="margin-left: 10px; color: #dc3545;">✗ Failed: ' + response.data.message + '</span>');
                    }
                    
                    setTimeout(() => $('.sffc-test-result').fadeOut(), 5000);
                },
                error: function() {
                    $button.after('<span class="sffc-test-result error" style="margin-left: 10px; color: #dc3545;">✗ Connection Error</span>');
                    setTimeout(() => $('.sffc-test-result').fadeOut(), 5000);
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    // Model Selection Information
    $('#sffc_claude_model').change(function() {
        const model = $(this).val();
        let info = '';
        
        switch(model) {
            case 'claude-3-5-sonnet-20241022':
                info = 'Latest model with enhanced financial reasoning and analysis capabilities. Recommended for complex market analysis.';
                break;
            case 'claude-3-opus-20240229':
                info = 'Highest intelligence model. Best for complex financial modeling and strategic analysis. Slower but most comprehensive.';
                break;
            case 'claude-3-sonnet-20240229':
                info = 'Balanced performance. Good for general financial advice and market commentary.';
                break;
            case 'claude-3-haiku-20240307':
                info = 'Fastest model. Best for quick responses and high-volume interactions. Lower cost.';
                break;
        }
        
        $('.model-info').remove();
        $(this).after('<p class="model-info description" style="font-style: italic; color: #666; margin-top: 5px;">' + info + '</p>');
    });
    
    // Temperature Guidelines
    $('#sffc_claude_temperature').on('input', function() {
        const temp = parseFloat($(this).val());
        let guidance = '';
        
        if (temp <= 0.3) {
            guidance = 'Very consistent, factual responses. Good for data analysis.';
        } else if (temp <= 0.5) {
            guidance = 'Consistent responses with some variation. Good for reports.';
        } else if (temp <= 0.7) {
            guidance = 'Balanced creativity and consistency. Recommended for financial advice.';
        } else if (temp <= 0.9) {
            guidance = 'More creative responses. Good for brainstorming and ideation.';
        } else {
            guidance = 'Highly creative responses. Use with caution for financial analysis.';
        }
        
        $('.temp-guidance').remove();
        $(this).parent().find('.description').after('<p class="temp-guidance" style="font-style: italic; color: #666; margin-top: 5px;">' + guidance + '</p>');
    });
    
    // Advanced Settings Toggle
    $('<div class="sffc-advanced-toggle">Show Advanced Configuration ▼</div>').insertBefore('.sffc-settings-section:has(h2:contains("Advanced Claude Configuration"))');
    
    $('.sffc-advanced-toggle').click(function() {
        $(this).toggleClass('collapsed');
        const $advancedSection = $('.sffc-settings-section:has(h2:contains("Advanced Claude Configuration"))');
        $advancedSection.slideToggle();
        
        if ($(this).hasClass('collapsed')) {
            $(this).text('Show Advanced Configuration ▶');
        } else {
            $(this).text('Hide Advanced Configuration ▼');
        }
    });
    
    // Initially hide advanced section
    $('.sffc-settings-section:has(h2:contains("Advanced Claude Configuration"))').hide();
    $('.sffc-advanced-toggle').addClass('collapsed').text('Show Advanced Configuration ▶');
    
    // Settings Validation
    $('form').submit(function(e) {
        const apiKey = $('#sffc_api_key').val().trim();
        const temperature = parseFloat($('#sffc_claude_temperature').val());
        
        // Validate API key format
        if (apiKey && !apiKey.startsWith('sk-ant-')) {
            alert('Claude API key should start with "sk-ant-". Please check your key.');
            e.preventDefault();
            return false;
        }
        
        // Validate temperature range
        if (temperature < 0 || temperature > 1) {
            alert('Temperature must be between 0 and 1.');
            e.preventDefault();
            return false;
        }
        
        return true;
    });
    
    // Auto-save draft settings
    let saveTimeout;
    $('.sffc-settings-section input, .sffc-settings-section select').change(function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            // Show "saving..." indicator
            if (!$('.sffc-auto-save').length) {
                $('h1').after('<div class="sffc-auto-save" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px 12px; margin: 10px 0; border-radius: 3px;">Auto-saving settings...</div>');
            }
            
            setTimeout(() => {
                $('.sffc-auto-save').fadeOut(() => $(this).remove());
            }, 2000);
        }, 1000);
    });
    
    // Enhanced Feed Management Functions
    
    // Add New Feed
    $('#sffc-add-feed').click(function(e) {
        e.preventDefault();
        
        const feedName = $('#new_feed_name').val().trim();
        const feedUrl = $('#new_feed_url').val().trim();
        const feedCategory = $('#new_feed_category').val();
        const feedPriority = $('#new_feed_priority').val();
        
        if (!feedName || !feedUrl) {
            alert('Please enter both feed name and URL.');
            return;
        }
        
        // Basic URL validation
        try {
            new URL(feedUrl);
        } catch (e) {
            alert('Please enter a valid URL.');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Adding...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_add_feed',
                nonce: $('input[name="_wpnonce"]').val(),
                feed_name: feedName,
                feed_url: feedUrl,
                feed_category: feedCategory,
                priority: feedPriority
            },
            success: function(response) {
                if (response.success) {
                    // Clear form
                    $('#new_feed_name, #new_feed_url').val('');
                    $('#new_feed_priority').val('10');
                    
                    // Show success message
                    $button.after('<span class="sffc-add-result success" style="margin-left: 10px; color: #28a745;">✓ Feed Added</span>');
                    
                    // Reload feeds table
                    location.reload();
                } else {
                    $button.after('<span class="sffc-add-result error" style="margin-left: 10px; color: #dc3545;">✗ Failed: ' + response.data + '</span>');
                }
                
                setTimeout(() => $('.sffc-add-result').fadeOut(), 5000);
            },
            error: function() {
                $button.after('<span class="sffc-add-result error" style="margin-left: 10px; color: #dc3545;">✗ Connection Error</span>');
                setTimeout(() => $('.sffc-add-result').fadeOut(), 5000);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test Feed URL (for new feeds)
    $('#sffc-test-feed').click(function(e) {
        e.preventDefault();
        
        const feedUrl = $('#new_feed_url').val().trim();
        
        if (!feedUrl) {
            alert('Please enter a feed URL first.');
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_test_feed_url',
                nonce: $('input[name="_wpnonce"]').val(),
                feed_url: feedUrl
            },
            success: function(response) {
                const $results = $('#sffc-feed-test-results');
                $results.show();
                
                if (response.success) {
                    const data = response.data;
                    $results.html(`
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 3px;">
                            <strong>✅ Feed Test Successful</strong><br>
                            <strong>Title:</strong> ${data.title || 'N/A'}<br>
                            <strong>Items Found:</strong> ${data.item_count || 0}<br>
                            <strong>Last Updated:</strong> ${data.last_updated || 'N/A'}<br>
                            <strong>Sample Items:</strong><br>
                            ${data.sample_items ? data.sample_items.map(item => `• ${item}`).join('<br>') : 'None'}
                        </div>
                    `);
                } else {
                    $results.html(`
                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 3px;">
                            <strong>❌ Feed Test Failed</strong><br>
                            <strong>Error:</strong> ${response.data || 'Unknown error'}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#sffc-feed-test-results').show().html(`
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 3px;">
                        <strong>❌ Connection Error</strong><br>
                        Unable to test feed URL. Please check your connection.
                    </div>
                `);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Test Individual Feeds in Table
    $(document).on('click', '.sffc-test-feed', function(e) {
        e.preventDefault();
        
        const feedId = $(this).data('feed-id');
        const $button = $(this);
        const originalText = $button.text();
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: (typeof sffc_settings !== 'undefined' && sffc_settings.ajax_url) ? sffc_settings.ajax_url : ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_test_existing_feed',
                nonce: ajax_nonce,
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    $button.after('<span class="sffc-test-result success" style="margin-left: 5px; color: #28a745;">✓</span>');
                } else {
                    $button.after('<span class="sffc-test-result error" style="margin-left: 5px; color: #dc3545;" title="' + response.data + '">✗</span>');
                }
                
                setTimeout(() => $('.sffc-test-result').fadeOut(), 3000);
            },
            error: function() {
                $button.after('<span class="sffc-test-result error" style="margin-left: 5px; color: #dc3545;">✗</span>');
                setTimeout(() => $('.sffc-test-result').fadeOut(), 3000);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Fetch Individual Feeds in Table Immediately
    $(document).on('click', '.sffc-fetch-feed-now', function(e) {
        e.preventDefault();

        const feedId = $(this).data('feed-id');
        const $button = $(this);
        const $row = $button.closest('tr');
        const originalText = $button.text();

        $button.prop('disabled', true).text('Fetching...');

        $.ajax({
            url: (typeof sffc_settings !== 'undefined' && sffc_settings.ajax_url) ? sffc_settings.ajax_url : ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_fetch_existing_feed_now',
                nonce: ajax_nonce,
                feed_id: feedId
            },
            success: function(response) {
                $('.sffc-fetch-result').remove();

                if (response.success) {
                    $row.find('.feed-last-fetched').text(response.data.last_fetched || 'just now');
                    if (response.data.status_html) {
                        $row.find('.feed-status').html(response.data.status_html);
                    }

                    $button.after(
                        '<span class="sffc-fetch-result success" style="margin-left: 8px; color: #28a745;">' +
                        (response.data.message || 'Fetched successfully') +
                        '</span>'
                    );
                } else {
                    const errorMessage = typeof response.data === 'string'
                        ? response.data
                        : ((response.data && response.data.message) ? response.data.message : 'Fetch failed');
                    $button.after(
                        '<span class="sffc-fetch-result error" style="margin-left: 8px; color: #dc3545;" title="' +
                        errorMessage.replace(/"/g, '&quot;') + '">' + errorMessage + '</span>'
                    );
                }

                setTimeout(() => $('.sffc-fetch-result').fadeOut(), 5000);
            },
            error: function(xhr) {
                let errorMessage = 'Fetch failed';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = typeof xhr.responseJSON.data === 'string' ? xhr.responseJSON.data : (xhr.responseJSON.data.message || errorMessage);
                } else if (xhr && xhr.responseText) {
                    errorMessage = xhr.responseText.substring(0, 180);
                }
                $button.after('<span class="sffc-fetch-result error" style="margin-left: 8px; color: #dc3545;">' + errorMessage + '</span>');
                setTimeout(() => $('.sffc-fetch-result').fadeOut(), 5000);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Toggle Feed Active Status
    $('.sffc-feed-active').change(function() {
        const feedId = $(this).data('feed-id');
        const isActive = $(this).is(':checked') ? 1 : 0;
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_toggle_feed_status',
                nonce: $('input[name="_wpnonce"]').val(),
                feed_id: feedId,
                is_active: isActive
            },
            success: function(response) {
                if (!response.success) {
                    alert('Failed to update feed status: ' + response.data);
                    // Revert checkbox state
                    $('.sffc-feed-active[data-feed-id="' + feedId + '"]').prop('checked', !isActive);
                }
            },
            error: function() {
                alert('Connection error. Please try again.');
                // Revert checkbox state
                $('.sffc-feed-active[data-feed-id="' + feedId + '"]').prop('checked', !isActive);
            }
        });
    });
    
    // Delete Feed
    $('.sffc-delete-feed').click(function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this feed?')) {
            return;
        }
        
        const feedId = $(this).data('feed-id');
        const $row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_delete_feed',
                nonce: $('input[name="_wpnonce"]').val(),
                feed_id: feedId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(() => $row.remove());
                } else {
                    alert('Failed to delete feed: ' + response.data);
                }
            },
            error: function() {
                alert('Connection error. Please try again.');
            }
        });
    });
    
    // Bulk Actions for Feeds - Select all checkbox
    $('#sffc-select-all-feeds').change(function() {
        $('.sffc-feed-select').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    // Update selected count when individual checkboxes change
    $('.sffc-feed-select').change(function() {
        updateSelectedCount();
        // Update "select all" state
        const allChecked = $('.sffc-feed-select:checked').length === $('.sffc-feed-select').length;
        $('#sffc-select-all-feeds').prop('checked', allChecked);
    });

    // Function to update selected count
    function updateSelectedCount() {
        const count = $('.sffc-feed-select:checked').length;
        if (count > 0) {
            $('#sffc-selected-count').text(count + ' feed(s) selected');
        } else {
            $('#sffc-selected-count').text('');
        }
    }

    // Apply bulk actions
    $('#sffc-apply-bulk-action').click(function() {
        const action = $('#sffc-bulk-action').val();
        const selectedFeeds = $('.sffc-feed-select:checked').map(function() {
            return $(this).data('feed-id');
        }).get();

        if (!action) {
            alert('Please select an action.');
            return;
        }

        if (selectedFeeds.length === 0) {
            alert('Please select at least one feed.');
            return;
        }

        // Confirmation for delete action
        if (action === 'delete') {
            if (!confirm('Are you sure you want to delete ' + selectedFeeds.length + ' feed(s)? This cannot be undone.')) {
                return;
            }
        }

        const $button = $(this);
        const originalText = $button.text();
        const $status = $('#sffc-bulk-status');

        $button.prop('disabled', true).text('Processing...');
        $status.text('Processing ' + selectedFeeds.length + ' feeds...').css('color', '#666');

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_bulk_feed_action',
                nonce: $('input[name="_wpnonce"]').val(),
                bulk_action: action,
                feed_ids: selectedFeeds
            },
            success: function(response) {
                if (response.success) {
                    $status.text('Success! ' + response.data.message).css('color', '#28a745');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.text('Failed: ' + response.data).css('color', '#dc3545');
                    alert('Bulk action failed: ' + response.data);
                }
            },
            error: function() {
                $status.text('Connection error').css('color', '#dc3545');
                alert('Connection error. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    if (!feedManagementEnabled) {
        return;
    }

    // Quick Add European Feeds Button - Multiple placement options
    const europeanFeedsHtml = '<div class="sffc-quick-actions" style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 15px; margin: 20px 0; border-radius: 5px;">' +
          '<h4 style="margin: 0 0 10px 0;">🇪🇺 Quick Add European Market Feeds</h4>' +
          '<p style="margin: 0 0 10px 0;">Add pre-configured European market, PE, and financial news feeds instantly.</p>' +
          '<button type="button" class="button button-primary" id="sffc-add-european-feeds">Add European Feeds</button> ' +
          '<button type="button" class="button button-secondary" id="sffc-preview-european-feeds">Preview Feeds</button>' +
          '</div>';
    
    // Try multiple placement options
    if ($('.sffc-add-feed-form').length > 0) {
        $(europeanFeedsHtml).insertAfter('.sffc-add-feed-form');
    } else if ($('#sffc-feeds-table').length > 0) {
        $(europeanFeedsHtml).insertBefore('#sffc-feeds-table');
    } else if ($('.sffc-settings-section:has(h2:contains("XML Feed Management"))').length > 0) {
        // If the XML Feed Management section exists but no table/form yet
        $(europeanFeedsHtml).appendTo('.sffc-settings-section:has(h2:contains("XML Feed Management"))');
    } else if ($('h3:contains("Existing Feeds")').length > 0) {
        // Alternative: place before existing feeds header
        $(europeanFeedsHtml).insertBefore('h3:contains("Existing Feeds")');
    }
    
    // Add European Feeds functionality (use event delegation for dynamically added button)
    $(document).on('click', '#sffc-add-european-feeds', function(e) {
        e.preventDefault();
        
        console.log('Add European Feeds clicked');
        console.log('AJAX URL:', ajaxurl);
        console.log('Nonce:', ajax_nonce);
        
        // Debug check
        if (!ajaxurl) {
            alert('Error: AJAX URL not defined. Please refresh the page and try again.');
            return;
        }
        
        if (!confirm('This will add 25+ European market and financial news feeds to your system. Continue?')) {
            return;
        }
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Adding Feeds...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_add_european_feeds_bulk',
                nonce: ajax_nonce
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    alert('Successfully added ' + response.data.added + ' European feeds!\n\n' +
                          'Added: ' + response.data.added + '\n' +
                          'Skipped: ' + response.data.skipped + '\n' +
                          'Errors: ' + response.data.errors);
                    location.reload();
                } else {
                    alert('Failed to add feeds: ' + (response.data || 'Unknown error'));
                    console.error('Error response:', response);
                }
            },
            error: function(xhr, status, error) {
                alert('Connection error: ' + error + '\n\nPlease check browser console for details.');
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Preview European Feeds (use event delegation for dynamically added button)
    $(document).on('click', '#sffc-preview-european-feeds', function(e) {
        e.preventDefault();
        
        console.log('Preview European Feeds clicked');
        
        const $button = $(this);
        const originalText = $button.text();
        $button.prop('disabled', true).text('Loading Preview...');
        
        // Use WordPress AJAX properly
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'sffc_preview_european_feeds',
                nonce: ajax_nonce
            },
            success: function(response) {
                console.log('Preview response:', response);
                if (response.success) {
                    // Open preview window with the content
                    const previewWindow = window.open('', 'FeedPreview', 'width=800,height=600,scrollbars=yes');
                    previewWindow.document.write('<html><head><title>European Feeds Preview</title></head><body style="font-family: Arial, sans-serif; padding: 20px;">');
                    previewWindow.document.write(response.data);
                    previewWindow.document.write('</body></html>');
                    previewWindow.document.close();
                } else {
                    alert('Failed to load preview: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Connection error: ' + error);
                console.error('AJAX Error:', status, error);
            },
            complete: function() {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
});
