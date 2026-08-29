/**
 * SEO Content Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Activate database tables
    $('#sffc-activate-tables, #sffc-activate-tables-notice').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Activating...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_activate_seo_tables',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Tables activated successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Activate Database Tables');
            }
        });
    });
    
    // Run news aggregation
    $('#sffc-aggregate-news').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Aggregating...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_manual_aggregate',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('News aggregation started. Check back in a few minutes for results.');
                $button.prop('disabled', false).text('Run News Aggregation');
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Run News Aggregation');
            }
        });
    });
    
    // Process queue
    $('#sffc-process-queue').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Processing...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_process_all_queue',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Queue processing started.');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Process Queue Now');
            }
        });
    });
    
    // Generate article form
    $('#sffc-generate-article-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $('#generation-progress').show();
        $('#generation-result').hide();
        
        var $progressFill = $('.progress-fill');
        var $progressStatus = $('.progress-status');
        
        // Simulate progress
        var progress = 0;
        var progressInterval = setInterval(function() {
            progress += 5;
            $progressFill.css('width', progress + '%');
            
            if (progress >= 30) {
                $progressStatus.text('Gathering sources...');
            }
            if (progress >= 50) {
                $progressStatus.text('Generating content with AI...');
            }
            if (progress >= 70) {
                $progressStatus.text('Optimizing for SEO...');
            }
            if (progress >= 90) {
                $progressStatus.text('Finalizing article...');
            }
            
            if (progress >= 95) {
                clearInterval(progressInterval);
            }
        }, 500);
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_generate_article',
            nonce: sffc_seo_admin.nonce,
            data: formData
        }, function(response) {
            clearInterval(progressInterval);
            $progressFill.css('width', '100%');
            
            if (response.success) {
                $progressStatus.text('Article generated successfully!');
                
                setTimeout(function() {
                    $('#generation-progress').hide();
                    $('#generation-result').show();
                    $('.article-preview').html(response.data.preview);
                    $('#generation-result').data('article-id', response.data.article_id);
                }, 1000);
            } else {
                $progressStatus.text('Error: ' + response.data.message);
                $progressFill.css('background', '#dc3545');
            }
        });
    });
    
    // Source type toggle
    $('input[name="source_type"]').on('change', function() {
        if ($(this).val() === 'manual') {
            $('#manual-sources').slideDown();
        } else {
            $('#manual-sources').slideUp();
        }
    });
    
    // Temperature slider
    $('#ai_temperature').on('input', function() {
        $('#temperature-value').text($(this).val());
    });
    
    // Fetch source
    $('.sffc-fetch-source').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var sourceId = $button.data('source-id');
        
        $button.prop('disabled', true).text('Fetching...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_fetch_source',
            nonce: sffc_seo_admin.nonce,
            source_id: sourceId
        }, function(response) {
            if (response.success) {
                alert('Source fetched successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Fetch Now');
            }
        });
    });
    
    // Toggle source
    $('.sffc-toggle-source').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var sourceId = $button.data('source-id');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_toggle_source',
            nonce: sffc_seo_admin.nonce,
            source_id: sourceId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Delete source
    $('.sffc-delete-source').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this source?')) {
            return;
        }
        
        var $button = $(this);
        var sourceId = $button.data('source-id');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_delete_source',
            nonce: sffc_seo_admin.nonce,
            source_id: sourceId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Process queue item
    $('.sffc-process-item').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var itemId = $button.data('item-id');
        
        $button.prop('disabled', true).text('Processing...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_process_queue_item',
            nonce: sffc_seo_admin.nonce,
            item_id: itemId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Process Now');
            }
        });
    });
    
    // Retry failed item
    $('.sffc-retry-item').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var itemId = $button.data('item-id');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_retry_queue_item',
            nonce: sffc_seo_admin.nonce,
            item_id: itemId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Delete queue item
    $('.sffc-delete-queue-item').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this queue item?')) {
            return;
        }
        
        var $button = $(this);
        var itemId = $button.data('item-id');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_delete_queue_item',
            nonce: sffc_seo_admin.nonce,
            item_id: itemId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Publish article
    $('#sffc-publish-article').on('click', function(e) {
        e.preventDefault();
        
        var articleId = $('#generation-result').data('article-id');
        
        if (!articleId) {
            alert('No article to publish');
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Publishing...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_publish_article',
            nonce: sffc_seo_admin.nonce,
            article_id: articleId
        }, function(response) {
            if (response.success) {
                alert('Article published successfully!');
                window.open(response.data.post_url, '_blank');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Publish Now');
            }
        });
    });
    
    // Schedule article
    $('#sffc-schedule-article').on('click', function(e) {
        e.preventDefault();
        
        var articleId = $('#generation-result').data('article-id');
        
        if (!articleId) {
            alert('No article to schedule');
            return;
        }
        
        var strategy = prompt('Enter scheduling strategy (immediate/scheduled/drip/optimal):', 'optimal');
        
        if (!strategy) {
            return;
        }
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_schedule_article',
            nonce: sffc_seo_admin.nonce,
            article_id: articleId,
            strategy: strategy
        }, function(response) {
            if (response.success) {
                alert('Article scheduled for: ' + response.data.scheduled_date);
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Process all queue items
    $('#sffc-process-all').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Process all pending items in the queue?')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('Processing...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_process_all_queue',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Queue processing started. This may take several minutes.');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Process All Pending');
            }
        });
    });
    
    // Clear failed items
    $('#sffc-clear-failed').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('Clear all failed items from the queue?')) {
            return;
        }
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_clear_failed_items',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
            }
        });
    });
    
    // Repair tables
    $('#sffc-repair-tables').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Repairing...');
        
        $.post(sffc_seo_admin.ajax_url, {
            action: 'sffc_repair_tables',
            nonce: sffc_seo_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Tables repaired successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                $button.prop('disabled', false).text('Repair Tables');
            }
        });
    });
    
    // Preview settings
    $('#sffc-preview-settings').on('click', function(e) {
        e.preventDefault();
        
        var settings = $('#sffc-generate-article-form').serializeArray();
        var preview = '<h3>Current Settings:</h3><ul>';
        
        $.each(settings, function(i, field) {
            if (field.value) {
                preview += '<li><strong>' + field.name + ':</strong> ' + field.value + '</li>';
            }
        });
        
        preview += '</ul>';
        
        var $modal = $('<div class="settings-preview-modal">' + preview + '</div>');
        $modal.dialog({
            title: 'Generation Settings',
            modal: true,
            width: 500,
            buttons: {
                Close: function() {
                    $(this).dialog('close');
                }
            }
        });
    });
});