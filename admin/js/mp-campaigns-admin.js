/**
 * MemberPress Campaigns Admin JavaScript
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        
        // Initialize components based on current page
        var currentPage = getUrlParameter('page');
        
        if (currentPage === 'memberpress-campaigns') {
            initDashboard();
        } else if (currentPage === 'mp-legacy-users') {
            initLegacyUsers();
        } else if (currentPage === 'mp-campaigns') {
            initCampaigns();
        } else if (currentPage === 'mp-email-templates') {
            initEmailTemplates();
        } else if (currentPage === 'mp-campaign-analytics') {
            initAnalytics();
        }
        
        // Common initializations
        initTooltips();
        initConfirmDialogs();
    });
    
    /**
     * Initialize Dashboard
     */
    function initDashboard() {
        // Load campaign performance chart
        loadPerformanceChart();
        
        // Auto-refresh stats every 30 seconds
        setInterval(refreshDashboardStats, 30000);
    }
    
    /**
     * Load performance chart
     */
    function loadPerformanceChart() {
        var ctx = document.getElementById('campaignPerformanceChart');
        if (!ctx) return;
        
        // Fetch data via AJAX
        $.post(mpCampaigns.ajaxurl, {
            action: 'sffc_get_campaign_stats',
            _ajax_nonce: mpCampaigns.nonce,
            period: '30_days'
        }, function(response) {
            if (response.success) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: response.data.labels,
                        datasets: [{
                            label: 'Emails Sent',
                            data: response.data.sent,
                            borderColor: '#D4AF37',
                            backgroundColor: 'rgba(212, 175, 55, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Opens',
                            data: response.data.opens,
                            borderColor: '#8B7355',
                            backgroundColor: 'rgba(139, 115, 85, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Conversions',
                            data: response.data.conversions,
                            borderColor: '#2C2C2C',
                            backgroundColor: 'rgba(44, 44, 44, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        });
    }
    
    /**
     * Refresh dashboard statistics
     */
    function refreshDashboardStats() {
        $.post(mpCampaigns.ajaxurl, {
            action: 'sffc_get_dashboard_stats',
            _ajax_nonce: mpCampaigns.nonce
        }, function(response) {
            if (response.success) {
                // Update stat cards
                $('.mp-stat-card').each(function() {
                    var stat = $(this).data('stat');
                    if (response.data[stat]) {
                        $(this).find('.mp-stat-number').text(response.data[stat]);
                    }
                });
            }
        });
    }
    
    /**
     * Initialize Legacy Users page
     */
    function initLegacyUsers() {
        // CSV Import
        $('#mp-csv-import-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'sffc_import_legacy_users');
            formData.append('import_type', 'csv');
            formData.append('_ajax_nonce', mpCampaigns.nonce);
            
            $.ajax({
                url: mpCampaigns.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    showLoader('Importing users...');
                },
                success: function(response) {
                    hideLoader();
                    
                    if (response.success) {
                        showNotice('success', response.data.message || mpCampaigns.strings.import_success);
                        
                        // Show preview if available
                        if (response.data.preview) {
                            showImportPreview(response.data.preview);
                        } else {
                            // Reload page to show imported users
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    } else {
                        showNotice('error', response.data || mpCampaigns.strings.import_error);
                    }
                },
                error: function() {
                    hideLoader();
                    showNotice('error', mpCampaigns.strings.import_error);
                }
            });
        });
        
        // Manual Import
        $('#mp-manual-import-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_import_legacy_users',
                import_type: 'manual',
                _ajax_nonce: mpCampaigns.nonce,
                data: formData
            }, function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || mpCampaigns.strings.import_success);
                    $('#mp-manual-import-form')[0].reset();
                    
                    // Reload table
                    loadLegacyUsersTable();
                } else {
                    showNotice('error', response.data || mpCampaigns.strings.import_error);
                }
            });
        });
        
        // MemberPress Import
        $('#mp-memberpress-import-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_import_legacy_users',
                import_type: 'memberpress',
                _ajax_nonce: mpCampaigns.nonce,
                data: formData
            }, function(response) {
                if (response.success) {
                    showNotice('success', 'Found ' + response.data.count + ' former subscribers');
                    showImportPreview(response.data.users);
                } else {
                    showNotice('error', response.data || 'No former subscribers found');
                }
            });
        });
        
        // Confirm import
        $('#mp-confirm-import').on('click', function() {
            var users = $(this).data('users');
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_confirm_legacy_import',
                _ajax_nonce: mpCampaigns.nonce,
                users: users
            }, function(response) {
                if (response.success) {
                    showNotice('success', 'Successfully imported ' + response.data.imported + ' users');
                    $('.mp-import-preview').hide();
                    loadLegacyUsersTable();
                }
            });
        });
        
        // Cancel import
        $('#mp-cancel-import').on('click', function() {
            $('.mp-import-preview').hide();
            $('#mp-import-preview-content').empty();
        });
        
        // Mark user as legacy manually
        $('.mp-mark-legacy').on('click', function() {
            var userId = $(this).data('user-id');
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_mark_user_as_legacy',
                _ajax_nonce: mpCampaigns.nonce,
                user_id: userId
            }, function(response) {
                if (response.success) {
                    showNotice('success', 'User marked as legacy');
                    loadLegacyUsersTable();
                }
            });
        });
    }
    
    /**
     * Show import preview
     */
    function showImportPreview(users) {
        var html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Email</th>';
        html += '<th>Name</th>';
        html += '<th>Original Tier</th>';
        html += '<th>Original Price</th>';
        html += '<th>Last Active</th>';
        html += '</tr></thead><tbody>';
        
        users.forEach(function(user) {
            html += '<tr>';
            html += '<td>' + user.email + '</td>';
            html += '<td>' + user.name + '</td>';
            html += '<td>' + user.tier + '</td>';
            html += '<td>$' + user.price + '</td>';
            html += '<td>' + user.last_active + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $('#mp-import-preview-content').html(html);
        $('.mp-import-preview').show();
        $('#mp-confirm-import').data('users', users);
    }
    
    /**
     * Initialize Campaigns page
     */
    function initCampaigns() {
        // Campaign form
        $('#mp-campaign-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_create_campaign',
                _ajax_nonce: mpCampaigns.nonce,
                data: formData
            }, function(response) {
                if (response.success) {
                    showNotice('success', mpCampaigns.strings.save_success);
                    
                    // Redirect to campaigns list
                    setTimeout(function() {
                        window.location.href = mpCampaigns.adminUrl + '?page=mp-campaigns';
                    }, 1500);
                } else {
                    showNotice('error', response.data || mpCampaigns.strings.save_error);
                }
            });
        });
        
        // Save as draft
        $('#mp-save-draft').on('click', function() {
            $('#campaign_status').val('draft');
            $('#mp-campaign-form').submit();
        });
        
        // Preview campaign
        $('#mp-preview-campaign').on('click', function() {
            var formData = $('#mp-campaign-form').serialize();
            
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_preview_campaign',
                _ajax_nonce: mpCampaigns.nonce,
                data: formData
            }, function(response) {
                if (response.success) {
                    showCampaignPreview(response.data);
                }
            });
        });
        
        // Campaign type change
        $('#campaign_type').on('change', function() {
            var type = $(this).val();
            loadCampaignTemplate(type);
        });
        
        // Offer type change
        $('#offer_type').on('change', function() {
            var type = $(this).val();
            updateOfferFields(type);
        });
        
        // Add email to sequence
        $('#mp-add-email').on('click', function() {
            addEmailToSequence();
        });
        
        // Load email template
        $('#mp-template-selector').on('change', function() {
            var template = $(this).val();
            if (template) {
                loadEmailSequenceTemplate(template);
            }
        });
        
        // Activate campaign
        $('.mp-activate-campaign').on('click', function() {
            var campaignId = $(this).data('campaign-id');
            
            if (confirm(mpCampaigns.strings.confirm_activate)) {
                $.post(mpCampaigns.ajaxurl, {
                    action: 'sffc_activate_campaign',
                    _ajax_nonce: mpCampaigns.nonce,
                    campaign_id: campaignId
                }, function(response) {
                    if (response.success) {
                        showNotice('success', 'Campaign activated');
                        location.reload();
                    }
                });
            }
        });
        
        // Pause campaign
        $('.mp-pause-campaign').on('click', function() {
            var campaignId = $(this).data('campaign-id');
            
            if (confirm(mpCampaigns.strings.confirm_pause)) {
                $.post(mpCampaigns.ajaxurl, {
                    action: 'sffc_pause_campaign',
                    _ajax_nonce: mpCampaigns.nonce,
                    campaign_id: campaignId
                }, function(response) {
                    if (response.success) {
                        showNotice('success', 'Campaign paused');
                        location.reload();
                    }
                });
            }
        });
        
        // Delete campaign
        $('.mp-delete-campaign').on('click', function() {
            var campaignId = $(this).data('campaign-id');
            
            if (confirm(mpCampaigns.strings.confirm_delete)) {
                $.post(mpCampaigns.ajaxurl, {
                    action: 'sffc_delete_campaign',
                    _ajax_nonce: mpCampaigns.nonce,
                    campaign_id: campaignId
                }, function(response) {
                    if (response.success) {
                        showNotice('success', 'Campaign deleted');
                        location.reload();
                    }
                });
            }
        });
    }
    
    /**
     * Add email to sequence
     */
    function addEmailToSequence() {
        var emailCount = $('.mp-email-item').length + 1;
        
        var html = '<div class="mp-email-item" data-email-index="' + emailCount + '">';
        html += '<div class="mp-email-item-header">';
        html += '<span class="mp-email-item-title">Email #' + emailCount + '</span>';
        html += '<div class="mp-email-item-controls">';
        html += '<button type="button" class="mp-edit-email" title="Edit"><span class="dashicons dashicons-edit"></span></button>';
        html += '<button type="button" class="mp-remove-email" title="Remove"><span class="dashicons dashicons-trash"></span></button>';
        html += '</div></div>';
        html += '<div class="mp-email-item-content">';
        html += '<input type="text" name="emails[' + emailCount + '][subject]" placeholder="Email subject" class="regular-text">';
        html += '<select name="emails[' + emailCount + '][delay]" style="margin-left: 10px;">';
        html += '<option value="0">Send immediately</option>';
        html += '<option value="1">After 1 day</option>';
        html += '<option value="3">After 3 days</option>';
        html += '<option value="7">After 7 days</option>';
        html += '<option value="14">After 14 days</option>';
        html += '</select>';
        html += '<input type="hidden" name="emails[' + emailCount + '][template]" value="">';
        html += '</div></div>';
        
        $('.mp-email-list').append(html);
        
        // Bind events
        bindEmailItemEvents();
    }
    
    /**
     * Load email sequence template
     */
    function loadEmailSequenceTemplate(template) {
        $.post(mpCampaigns.ajaxurl, {
            action: 'sffc_get_email_sequence_template',
            _ajax_nonce: mpCampaigns.nonce,
            template: template
        }, function(response) {
            if (response.success) {
                $('.mp-email-list').html(response.data.html);
                bindEmailItemEvents();
            }
        });
    }
    
    /**
     * Bind email item events
     */
    function bindEmailItemEvents() {
        // Remove email
        $('.mp-remove-email').off('click').on('click', function() {
            $(this).closest('.mp-email-item').remove();
            reindexEmailItems();
        });
        
        // Edit email
        $('.mp-edit-email').off('click').on('click', function() {
            var emailItem = $(this).closest('.mp-email-item');
            openEmailEditor(emailItem);
        });
    }
    
    /**
     * Reindex email items
     */
    function reindexEmailItems() {
        $('.mp-email-item').each(function(index) {
            var newIndex = index + 1;
            $(this).attr('data-email-index', newIndex);
            $(this).find('.mp-email-item-title').text('Email #' + newIndex);
            
            // Update input names
            $(this).find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/emails\[\d+\]/, 'emails[' + newIndex + ']');
                    $(this).attr('name', name);
                }
            });
        });
    }
    
    /**
     * Initialize Email Templates
     */
    function initEmailTemplates() {
        // New template
        $('#mp-new-template').on('click', function(e) {
            e.preventDefault();
            openTemplateEditor();
        });
        
        // Edit template
        $('.mp-template-card').on('click', function() {
            var templateId = $(this).data('template-id');
            openTemplateEditor(templateId);
        });
        
        // Template editor tabs
        $('.mp-tab-button').on('click', function() {
            var tab = $(this).data('tab');
            
            $('.mp-tab-button').removeClass('active');
            $(this).addClass('active');
            
            $('.mp-tab-content').hide();
            $('#mp-' + tab + '-tab').show();
            
            if (tab === 'preview') {
                updateTemplatePreview();
            }
        });
        
        // Save template
        $('#mp-save-template').on('click', function() {
            saveTemplate();
        });
        
        // Cancel template
        $('#mp-cancel-template').on('click', function() {
            $('#mp-template-modal').hide();
        });
        
        // Close modal
        $('.mp-modal-close').on('click', function() {
            $(this).closest('.mp-modal').hide();
        });
    }
    
    /**
     * Open template editor
     */
    function openTemplateEditor(templateId) {
        $('#mp-template-modal').show();
        
        if (templateId) {
            // Load existing template
            $.post(mpCampaigns.ajaxurl, {
                action: 'sffc_get_email_template',
                _ajax_nonce: mpCampaigns.nonce,
                template_id: templateId
            }, function(response) {
                if (response.success) {
                    $('#mp-template-html').val(response.data.html);
                    $('#mp-template-modal').data('template-id', templateId);
                }
            });
        } else {
            // Load default template
            loadDefaultTemplate();
        }
    }
    
    /**
     * Update template preview
     */
    function updateTemplatePreview() {
        var html = $('#mp-template-html').val();
        var iframe = document.getElementById('mp-template-preview');
        var doc = iframe.contentDocument || iframe.contentWindow.document;
        
        doc.open();
        doc.write(html);
        doc.close();
    }
    
    /**
     * Save template
     */
    function saveTemplate() {
        var templateId = $('#mp-template-modal').data('template-id') || 0;
        var html = $('#mp-template-html').val();
        
        $.post(mpCampaigns.ajaxurl, {
            action: 'sffc_save_email_template',
            _ajax_nonce: mpCampaigns.nonce,
            template_id: templateId,
            html: html
        }, function(response) {
            if (response.success) {
                showNotice('success', 'Template saved successfully');
                $('#mp-template-modal').hide();
                location.reload();
            } else {
                showNotice('error', 'Error saving template');
            }
        });
    }
    
    /**
     * Initialize Analytics
     */
    function initAnalytics() {
        // Load analytics charts
        loadAnalyticsCharts();
        
        // Date range selector
        $('#mp-analytics-range').on('change', function() {
            var range = $(this).val();
            loadAnalyticsCharts(range);
        });
        
        // Export data
        $('#mp-export-analytics').on('click', function() {
            var range = $('#mp-analytics-range').val();
            
            window.location.href = mpCampaigns.ajaxurl + '?action=sffc_export_analytics&range=' + range + '&_wpnonce=' + mpCampaigns.nonce;
        });
    }
    
    /**
     * Load analytics charts
     */
    function loadAnalyticsCharts(range) {
        range = range || '30_days';
        
        $.post(mpCampaigns.ajaxurl, {
            action: 'sffc_get_analytics_data',
            _ajax_nonce: mpCampaigns.nonce,
            range: range
        }, function(response) {
            if (response.success) {
                renderAnalyticsCharts(response.data);
            }
        });
    }
    
    /**
     * Helper Functions
     */
    
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('.wrap').prepend(notice);
        
        setTimeout(function() {
            $('.notice').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    function showLoader(message) {
        message = message || 'Loading...';
        var loader = '<div class="mp-loader-overlay"><div class="mp-loader">' + message + '</div></div>';
        $('body').append(loader);
    }
    
    function hideLoader() {
        $('.mp-loader-overlay').remove();
    }
    
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
    function initTooltips() {
        // Initialize tooltips if needed
    }
    
    function initConfirmDialogs() {
        // Initialize confirmation dialogs
    }
    
})(jQuery);