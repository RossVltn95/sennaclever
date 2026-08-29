/**
 * Dynamic Post Type Filters Admin JavaScript
 * Admin interface for creating filters from custom post types
 * 
 * @package SkillFarmFinance
 * @since 10.20.0
 */

(function($) {
    'use strict';
    
    class DynamicPostTypeFiltersAdmin {
        constructor() {
            this.config = window.sffc_dynamic_admin || {
                ajax_url: '/wp-admin/admin-ajax.php',
                nonce: 'fallback_nonce'
            };
            this.isEditing = false;
            this.currentEditKey = null;
            
            console.log('🔧 Dynamic Post Type Filters Admin: Initializing...');
            console.log('🔧 Config object:', this.config);
            console.log('🔧 AJAX URL:', this.config.ajax_url);
            console.log('🔧 Nonce:', this.config.nonce);
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initColorPickers();
            this.initModals();
            this.initSortable();
            this.initBulkActions();
            this.debugButtonsAvailability();
        }
        
        debugButtonsAvailability() {
            console.log('🔧 === BUTTON AVAILABILITY DEBUG ===');
            
            // Check if buttons exist in DOM
            const buttons = {
                'create-default-filters': $('#create-default-filters'),
                'create-default-filters-force': $('#create-default-filters-force'),
                'clear-all-filters': $('#clear-all-filters'),
                'open-icon-picker': $('#open-icon-picker')
            };
            
            Object.entries(buttons).forEach(([id, $btn]) => {
                console.log(`🔧 Button #${id}:`, {
                    exists: $btn.length > 0,
                    visible: $btn.is(':visible'),
                    disabled: $btn.prop('disabled'),
                    classes: $btn.attr('class'),
                    element: $btn[0]
                });
            });
            
            // Check by class selectors
            console.log('🔧 Class selectors:');
            console.log('🔧 .create-defaults-btn count:', $('.create-defaults-btn').length);
            console.log('🔧 .icon-picker-btn count:', $('.icon-picker-btn').length);
            
            console.log('🔧 === END BUTTON DEBUG ===');
        }
        
        bindEvents() {
            // Filter Management
            $(document).on('click', '#add-post-type-filter, #add-first-filter', () => {
                this.openFilterModal();
            });
            
            $(document).on('click', '.edit-filter', (e) => {
                const filterKey = $(e.target).data('filter-key');
                this.editFilter(filterKey);
            });
            
            $(document).on('click', '.delete-filter', (e) => {
                const filterKey = $(e.target).data('filter-key');
                this.deleteFilter(filterKey);
            });
            
            $(document).on('click', '.test-filter', (e) => {
                const filterKey = $(e.target).data('filter-key');
                this.testFilter(filterKey);
            });
            
            $(document).on('submit', '#post-type-filter-form', (e) => {
                e.preventDefault();
                this.saveFilter();
            });
            
            // Modal events
            $(document).on('click', '.sffc-modal-close', () => {
                this.closeModals();
            });
            
            $(document).on('click', '.sffc-modal', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModals();
                }
            });
            
            // Post type selection - auto-populate some fields
            $(document).on('change', '#post-type', (e) => {
                this.handlePostTypeChange($(e.target).val());
            });
            
            // Create default filters button
            $(document).on('click', '#create-default-filters', (e) => {
                console.log('🔧 Create Default Filters button clicked');
                e.preventDefault();
                this.createDefaultFilters();
            });
            
            // Icon picker events
            $(document).on('click', '#open-icon-picker', (e) => {
                console.log('🔧 Icon Picker button clicked');
                e.preventDefault();
                this.openIconPicker();
            });
            
            $(document).on('click', '.icon-tab', (e) => {
                this.switchIconTab($(e.target).data('tab'));
            });
            
            $(document).on('click', '.icon-option', (e) => {
                this.selectPredefinedIcon($(e.target));
            });
            
            $(document).on('click', '.text-icon-option', (e) => {
                this.selectTextIcon($(e.target));
            });
            
            $(document).on('input', '#custom-svg-input', () => {
                this.previewCustomSVG();
            });
            
            $(document).on('click', '#use-custom-svg', () => {
                this.useCustomSVG();
            });
            
            $(document).on('click', '#use-custom-text', () => {
                this.useCustomText();
            });
            
            $(document).on('input', '#filter-icon', () => {
                this.updateIconPreview();
            });
            
            // Add event delegation for class-based selectors
            $(document).on('click', '.create-defaults-btn', (e) => {
                console.log('🔧 Create Defaults button clicked (class selector)');
                const buttonId = $(e.target).attr('id');
                console.log('🔧 Button ID:', buttonId);
                e.preventDefault();
                
                if (buttonId === 'create-default-filters') {
                    this.createDefaultFilters();
                } else if (buttonId === 'create-default-filters-force') {
                    this.createDefaultFilters('debug-status');
                } else if (buttonId === 'clear-all-filters') {
                    this.clearAllFilters();
                }
            });
            
            $(document).on('click', '.icon-picker-btn', (e) => {
                console.log('🔧 Icon Picker button clicked (class selector)');
                e.preventDefault();
                this.openIconPicker();
            });
            
            // Debug buttons
            $(document).on('click', '#create-default-filters-force', (e) => {
                console.log('🔧 Force Create button clicked');
                e.preventDefault();
                this.createDefaultFilters('debug-status');
            });
            
            $(document).on('click', '#clear-all-filters', (e) => {
                console.log('🔧 Clear All button clicked');
                e.preventDefault();
                this.clearAllFilters();
            });
        }
        
        initColorPickers() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        }
        
        initModals() {
            $('.sffc-modal').hide();
        }
        
        openFilterModal(filterKey = null) {
            this.isEditing = !!filterKey;
            this.currentEditKey = filterKey;
            
            const modal = $('#post-type-filter-modal');
            const title = this.isEditing ? 'Edit Post Type Filter' : 'Add Post Type Filter';
            
            modal.find('#modal-title').text(title);
            
            if (this.isEditing) {
                this.populateFilterForm(filterKey);
            } else {
                this.resetFilterForm();
            }
            
            modal.show();
        }
        
        populateFilterForm(filterKey) {
            // Get data from the table row
            const row = $(`tr[data-filter-key="${filterKey}"]`);
            
            // Extract data from row
            const filterLabel = row.find('td:first strong').text().replace(/^[^\s]+\s/, ''); // Remove icon
            const postType = row.find('code').text();
            
            // Populate basic fields
            $('#filter-key').val(filterKey).prop('readonly', true);
            $('#filter-label').val(filterLabel);
            $('#post-type').val(postType);
            
            // Set defaults for other fields (in real app, these would come from database)
            $('#filter-icon').val('<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>');
            $('#filter-color').val('#3b82f6');
            $('#filter-priority').val('10');
            $('#filter-active').prop('checked', true);
            
            // Set field mapping defaults
            $('#title-field').val('post_title');
            $('#summary-field').val('post_excerpt');
            
            // Update color picker
            if ($.fn.wpColorPicker) {
                $('#filter-color').wpColorPicker('color', '#3b82f6');
            }
        }
        
        resetFilterForm() {
            $('#post-type-filter-form')[0].reset();
            $('#filter-key').prop('readonly', false);
            $('#filter-priority').val('10');
            $('#filter-active').prop('checked', true);
            $('#filter-icon').val('<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>');
            $('#title-field').val('post_title');
            $('#summary-field').val('post_excerpt');
            
            if ($.fn.wpColorPicker) {
                $('#filter-color').wpColorPicker('color', '#3b82f6');
            }
        }
        
        handlePostTypeChange(postType) {
            if (!postType) return;
            
            // Auto-populate filter key and label based on post type
            if (!this.isEditing) {
                const filterKey = postType.replace('sffc_', '').replace('_', '');
                const label = this.capitalizeWords(postType.replace('sffc_', '').replace('_', ' '));
                
                $('#filter-key').val(filterKey);
                $('#filter-label').val(label + ' Intelligence');
            }
            
            // Show helpful suggestions based on post type
            this.showPostTypeHelp(postType);
        }
        
        showPostTypeHelp(postType) {
            let suggestions = '';
            
            switch (postType) {
                case 'sffc_market':
                    suggestions = `
                        <p><strong>Suggested fields for markets:</strong></p>
                        <ul>
                            <li>Metric Field: <code>market_value</code></li>
                            <li>Change Field: <code>market_change</code></li>
                            <li>Category Field: <code>market_category</code></li>
                            <li>Source Field: <code>data_source</code></li>
                        </ul>
                    `;
                    break;
                    
                case 'sffc_deal':
                    suggestions = `
                        <p><strong>Suggested fields for deals:</strong></p>
                        <ul>
                            <li>Metric Field: <code>deal_value</code></li>
                            <li>Change Field: <code>deal_status</code></li>
                            <li>Category Field: <code>deal_type</code></li>
                            <li>Source Field: <code>deal_source</code></li>
                        </ul>
                    `;
                    break;
                    
                default:
                    suggestions = `
                        <p><strong>Common field patterns:</strong></p>
                        <ul>
                            <li>Metric Field: <code>${postType}_value</code></li>
                            <li>Category Field: <code>${postType}_category</code></li>
                            <li>Source Field: <code>data_source</code></li>
                        </ul>
                    `;
                    break;
            }
            
            // You could add a help section to the form here
        }
        
        saveFilter() {
            const formData = this.getFilterFormData();
            
            if (!this.validateFilterForm(formData)) {
                return;
            }
            
            this.showSaving();
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_save_post_type_filter',
                    nonce: this.config.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Filter configuration saved successfully');
                        this.closeModals();
                        this.refreshPage();
                    } else {
                        this.showError(response.data || 'Error saving filter configuration');
                    }
                },
                error: () => {
                    this.showError('Network error occurred');
                },
                complete: () => {
                    this.hideSaving();
                }
            });
        }
        
        deleteFilter(filterKey) {
            if (!confirm('Are you sure you want to delete this filter? This cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_delete_post_type_filter',
                    nonce: this.config.nonce,
                    filter_key: filterKey
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Filter deleted successfully');
                        $(`tr[data-filter-key="${filterKey}"]`).fadeOut(() => {
                            $(this).remove();
                        });
                    } else {
                        this.showError(response.data || 'Error deleting filter');
                    }
                },
                error: () => {
                    this.showError('Network error occurred');
                }
            });
        }
        
        testFilter(filterKey) {
            console.log('🧪 Testing filter:', filterKey);
            
            // Show test modal
            const modal = $('#test-results-modal');
            modal.find('#test-results-content').html('<div class="loading">Testing filter configuration...</div>');
            modal.show();
            
            // Make test AJAX call
            $.ajax({
                url: this.config.ajax_url.replace('admin-ajax.php', 'admin-ajax.php'),
                type: 'POST',
                data: {
                    action: 'sffc_dynamic_post_filter',
                    filter_key: filterKey,
                    limit: 3, // Just show a few for testing
                    nonce: this.createTestNonce()
                },
                success: (response) => {
                    let resultHtml = '';
                    
                    if (response.success) {
                        resultHtml = `
                            <div class="test-success">
                                <h4>✅ Filter Test Successful</h4>
                                <p><strong>Filter:</strong> ${filterKey}</p>
                                <p><strong>Cards Found:</strong> ${response.data.count}</p>
                                <p><strong>Post Type:</strong> ${response.data.post_type}</p>
                                
                                <h4>Preview:</h4>
                                <div class="test-cards-preview">
                                    ${response.data.html}
                                </div>
                            </div>
                        `;
                    } else {
                        resultHtml = `
                            <div class="test-error">
                                <h4>❌ Filter Test Failed</h4>
                                <p><strong>Error:</strong> ${response.data}</p>
                                
                                <h4>Troubleshooting:</h4>
                                <ul>
                                    <li>Make sure the post type exists and has published posts</li>
                                    <li>Check that field mappings are correct</li>
                                    <li>Verify required meta fields exist on posts</li>
                                </ul>
                            </div>
                        `;
                    }
                    
                    modal.find('#test-results-content').html(resultHtml);
                },
                error: (xhr, status, error) => {
                    const resultHtml = `
                        <div class="test-error">
                            <h4>❌ Network Error</h4>
                            <p><strong>Error:</strong> ${error}</p>
                            <p>Unable to test filter. Check console for details.</p>
                        </div>
                    `;
                    modal.find('#test-results-content').html(resultHtml);
                }
            });
        }
        
        createTestNonce() {
            // In a real implementation, this would need to be a proper nonce
            // For now, return a placeholder
            return 'test_nonce';
        }
        
        getFilterFormData() {
            return {
                filter_key: $('#filter-key').val().trim(),
                label: $('#filter-label').val().trim(),
                post_type: $('#post-type').val(),
                icon: $('#filter-icon').val().trim(),
                color: $('#filter-color').val(),
                priority: parseInt($('#filter-priority').val()) || 10,
                active: $('#filter-active').prop('checked'),
                title_field: $('#title-field').val().trim(),
                summary_field: $('#summary-field').val().trim(),
                metric_field: $('#metric-field').val().trim(),
                change_field: $('#change-field').val().trim(),
                category_field: $('#category-field').val().trim(),
                source_field: $('#source-field').val().trim(),
                required_meta: $('#required-meta').val().trim()
            };
        }
        
        validateFilterForm(data) {
            if (!data.filter_key) {
                this.showError('Filter key is required');
                return false;
            }
            
            if (!/^[a-z0-9_]+$/.test(data.filter_key)) {
                this.showError('Filter key must contain only lowercase letters, numbers, and underscores');
                return false;
            }
            
            if (!data.label) {
                this.showError('Display label is required');
                return false;
            }
            
            if (!data.post_type) {
                this.showError('Post type is required');
                return false;
            }
            
            return true;
        }
        
        capitalizeWords(str) {
            return str.replace(/\w\S*/g, (txt) => {
                return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
            });
        }
        
        // UI Helper Methods
        closeModals() {
            $('.sffc-modal').hide();
            this.isEditing = false;
            this.currentEditKey = null;
        }
        
        showSaving() {
            $('button[type="submit"]').prop('disabled', true).text('Saving...');
        }
        
        hideSaving() {
            $('button[type="submit"]').prop('disabled', false).text('Save Filter Configuration');
        }
        
        showSuccess(message) {
            this.showNotice(message, 'success');
        }
        
        showError(message) {
            this.showNotice(message, 'error');
        }
        
        showNotice(message, type = 'info') {
            const notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.fadeOut(() => notice.remove());
            }, 5000);
            
            // Manual dismiss
            notice.on('click', '.notice-dismiss', () => {
                notice.fadeOut(() => notice.remove());
            });
        }
        
        refreshPage() {
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        editFilter(filterKey) {
            this.openFilterModal(filterKey);
        }
        
        createDefaultFilters(statusDivId = 'defaults-status') {
            console.log('🔧 Creating default filters...');
            console.log('🔧 Status div ID:', statusDivId);
            console.log('🔧 Config check:', this.config);
            
            if (!this.config.ajax_url) {
                console.error('🔧 No AJAX URL available!');
                alert('Error: AJAX configuration missing. Please refresh the page.');
                return;
            }
            
            const button = statusDivId === 'debug-status' ? $('#create-default-filters-force') : $('#create-default-filters');
            const statusDiv = $(`#${statusDivId}`);
            
            console.log('🔧 Button found:', button.length);
            console.log('🔧 Status div found:', statusDiv.length);
            
            // Show loading state
            button.prop('disabled', true).text('Creating filters...');
            statusDiv.html('');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_create_default_filters',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        statusDiv.html(`
                            <div class="defaults-status success">
                                <strong>✅ Success!</strong> Created ${response.data.filters_created} default filters: ${response.data.filters.join(', ')}<br>
                                <em>Refresh this page to see the filters in the table.</em>
                            </div>
                        `);
                        
                        // Auto-refresh after 2 seconds
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        
                    } else {
                        statusDiv.html(`
                            <div class="defaults-status error">
                                <strong>❌ Error:</strong> ${response.data || 'Failed to create default filters'}
                            </div>
                        `);
                    }
                },
                error: (xhr, status, error) => {
                    statusDiv.html(`
                        <div class="defaults-status error">
                            <strong>❌ Network Error:</strong> ${error}
                        </div>
                    `);
                },
                complete: () => {
                    const originalText = statusDivId === 'debug-status' ? 'Force Create Default Filters' : 'Create Default Filters';
                    button.prop('disabled', false).text(originalText);
                }
            });
        }
        
        clearAllFilters() {
            if (!confirm('Are you sure you want to clear ALL filters? This cannot be undone.')) {
                return;
            }
            
            console.log('🗑️ Clearing all filters...');
            
            const button = $('#clear-all-filters');
            const statusDiv = $('#debug-status');
            
            // Show loading state
            button.prop('disabled', true).text('Clearing...');
            statusDiv.html('');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_clear_all_filters',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        statusDiv.html(`
                            <div class="defaults-status success">
                                <strong>✅ Success!</strong> All filters cleared.<br>
                                <em>Refresh this page to see the changes.</em>
                            </div>
                        `);
                        
                        // Auto-refresh after 2 seconds
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                        
                    } else {
                        statusDiv.html(`
                            <div class="defaults-status error">
                                <strong>❌ Error:</strong> ${response.data || 'Failed to clear filters'}
                            </div>
                        `);
                    }
                },
                error: (xhr, status, error) => {
                    statusDiv.html(`
                        <div class="defaults-status error">
                            <strong>❌ Network Error:</strong> ${error}
                        </div>
                    `);
                },
                complete: () => {
                    button.prop('disabled', false).text('Clear All Filters');
                }
            });
        }
        
        initSortable() {
            if ($('#filters-sortable').length && typeof $.ui !== 'undefined' && $.ui.sortable) {
                $('#filters-sortable').sortable({
                    handle: '.reorder-handle',
                    placeholder: 'sortable-placeholder',
                    helper: 'clone',
                    opacity: 0.7,
                    tolerance: 'pointer',
                    update: (event, ui) => {
                        this.saveFilterOrder();
                    }
                });
            } else {
                console.log('🔧 jQuery UI Sortable not available - drag & drop disabled');
            }
        }
        
        initBulkActions() {
            // Select all checkbox
            $(document).on('change', '#select-all-filters', (e) => {
                const checked = $(e.target).prop('checked');
                $('.filter-checkbox').prop('checked', checked);
                this.updateBulkActionButtons();
            });
            
            // Individual checkboxes
            $(document).on('change', '.filter-checkbox', () => {
                this.updateBulkActionButtons();
            });
            
            // Bulk action buttons
            $(document).on('click', '#bulk-delete-filters', () => {
                this.bulkDeleteFilters();
            });
            
            $(document).on('click', '#bulk-activate-filters', () => {
                this.bulkToggleFilters(true);
            });
            
            $(document).on('click', '#bulk-deactivate-filters', () => {
                this.bulkToggleFilters(false);
            });
        }
        
        updateBulkActionButtons() {
            const checkedBoxes = $('.filter-checkbox:checked');
            const count = checkedBoxes.length;
            
            $('#bulk-delete-filters, #bulk-activate-filters, #bulk-deactivate-filters')
                .prop('disabled', count === 0);
            
            $('.bulk-actions-info').text(
                count > 0 ? `${count} filter${count > 1 ? 's' : ''} selected` : ''
            );
        }
        
        saveFilterOrder() {
            const order = [];
            $('#filters-sortable tr').each(function(index) {
                const filterKey = $(this).data('filter-key');
                if (filterKey) {
                    order.push({
                        key: filterKey,
                        priority: index + 1
                    });
                }
            });
            
            console.log('🔄 Saving filter order:', order);
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_reorder_filters',
                    nonce: this.config.nonce,
                    order: order
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Filter order saved successfully');
                        // Update priority numbers in the UI
                        $('#filters-sortable tr').each(function(index) {
                            $(this).find('.reorder-handle small').text(index + 1);
                        });
                    } else {
                        this.showError(response.data || 'Failed to save filter order');
                    }
                },
                error: () => {
                    this.showError('Network error saving filter order');
                }
            });
        }
        
        bulkDeleteFilters() {
            const selectedKeys = $('.filter-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedKeys.length === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${selectedKeys.length} filter${selectedKeys.length > 1 ? 's' : ''}? This cannot be undone.`)) {
                return;
            }
            
            console.log('🗑️ Bulk deleting filters:', selectedKeys);
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_bulk_delete_filters',
                    nonce: this.config.nonce,
                    filter_keys: selectedKeys
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(`Successfully deleted ${selectedKeys.length} filter${selectedKeys.length > 1 ? 's' : ''}`);
                        // Remove rows from table
                        selectedKeys.forEach(key => {
                            $(`tr[data-filter-key="${key}"]`).fadeOut(() => {
                                $(this).remove();
                            });
                        });
                        this.updateBulkActionButtons();
                    } else {
                        this.showError(response.data || 'Failed to delete filters');
                    }
                },
                error: () => {
                    this.showError('Network error deleting filters');
                }
            });
        }
        
        bulkToggleFilters(activate) {
            const selectedKeys = $('.filter-checkbox:checked').map(function() {
                return $(this).val();
            }).get();
            
            if (selectedKeys.length === 0) return;
            
            const action = activate ? 'activate' : 'deactivate';
            console.log(`🔄 Bulk ${action} filters:`, selectedKeys);
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_bulk_toggle_filters',
                    nonce: this.config.nonce,
                    filter_keys: selectedKeys,
                    activate: activate
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(`Successfully ${action}d ${selectedKeys.length} filter${selectedKeys.length > 1 ? 's' : ''}`);
                        // Update status badges in the UI
                        selectedKeys.forEach(key => {
                            const row = $(`tr[data-filter-key="${key}"]`);
                            const badge = row.find('.status-badge');
                            if (activate) {
                                badge.removeClass('inactive').addClass('active').text('Active');
                            } else {
                                badge.removeClass('active').addClass('inactive').text('Inactive');
                            }
                        });
                        this.updateBulkActionButtons();
                    } else {
                        this.showError(response.data || `Failed to ${action} filters`);
                    }
                },
                error: () => {
                    this.showError(`Network error ${action}ing filters`);
                }
            });
        }
        
        // Icon Picker Methods
        openIconPicker() {
            console.log('🎨 Opening icon picker modal');
            $('#icon-picker-modal').show();
            this.switchIconTab('predefined'); // Default to predefined icons
            this.updateIconPreview(); // Update preview based on current input
        }
        
        switchIconTab(tabName) {
            console.log('🎨 Switching to tab:', tabName);
            
            // Update tab buttons
            $('.icon-tab').removeClass('active');
            $(`.icon-tab[data-tab="${tabName}"]`).addClass('active');
            
            // Show/hide tab content
            $('.icon-tab-content').hide();
            $(`#tab-${tabName}`).show();
        }
        
        selectPredefinedIcon(iconElement) {
            const iconData = iconElement.data('icon');
            if (!iconData) {
                // Try to find the closest parent with icon data
                const parentOption = iconElement.closest('.icon-option');
                if (parentOption.length) {
                    iconData = parentOption.data('icon');
                }
            }
            
            if (iconData) {
                console.log('🎨 Selected predefined icon:', iconData);
                
                // Update the icon input field
                $('#filter-icon').val(iconData);
                
                // Update visual selection
                $('.icon-option').removeClass('selected');
                iconElement.closest('.icon-option').addClass('selected');
                
                // Update preview
                this.updateIconPreview();
                
                // Close modal
                $('#icon-picker-modal').hide();
            }
        }
        
        selectTextIcon(textElement) {
            const iconText = textElement.data('icon') || textElement.text().trim();
            
            console.log('🎨 Selected text icon:', iconText);
            
            // Update the icon input field
            $('#filter-icon').val(iconText);
            
            // Update visual selection
            $('.text-icon-option').removeClass('selected');
            textElement.addClass('selected');
            
            // Update preview
            this.updateIconPreview();
            
            // Close modal
            $('#icon-picker-modal').hide();
        }
        
        previewCustomSVG() {
            const svgInput = $('#custom-svg-input').val().trim();
            const previewArea = $('#custom-svg-preview-area');
            
            if (svgInput && svgInput.includes('<svg')) {
                // Basic SVG validation
                if (this.isValidSVG(svgInput)) {
                    previewArea.html(svgInput);
                    console.log('🎨 Custom SVG preview updated');
                } else {
                    previewArea.html('<span style="color: red; font-size: 12px;">Invalid SVG</span>');
                }
            } else {
                previewArea.html('<span style="color: #666; font-size: 12px;">No SVG</span>');
            }
        }
        
        isValidSVG(svgString) {
            // Basic SVG validation
            return svgString.includes('<svg') && 
                   svgString.includes('</svg>') && 
                   !svgString.includes('<script') && 
                   !svgString.includes('javascript:') &&
                   !svgString.includes('on');
        }
        
        useCustomSVG() {
            const svgInput = $('#custom-svg-input').val().trim();
            
            if (!svgInput) {
                alert('Please enter SVG code first.');
                return;
            }
            
            if (!this.isValidSVG(svgInput)) {
                alert('Please enter valid SVG code. Make sure it includes <svg> tags and no scripts.');
                return;
            }
            
            console.log('🎨 Using custom SVG:', svgInput.substring(0, 50) + '...');
            
            // Update the icon input field
            $('#filter-icon').val(svgInput);
            
            // Update preview
            this.updateIconPreview();
            
            // Close modal
            $('#icon-picker-modal').hide();
        }
        
        useCustomText() {
            const customText = $('#custom-text-input').val().trim();
            
            if (!customText) {
                alert('Please enter custom text first.');
                return;
            }
            
            if (customText.length > 6) {
                alert('Custom text should be 6 characters or less for best display.');
                return;
            }
            
            console.log('🎨 Using custom text:', customText);
            
            // Update the icon input field
            $('#filter-icon').val(customText);
            
            // Update preview
            this.updateIconPreview();
            
            // Close modal
            $('#icon-picker-modal').hide();
        }
        
        updateIconPreview() {
            const iconValue = $('#filter-icon').val().trim();
            const previewIcon = $('.preview-icon');
            
            if (!iconValue) {
                previewIcon.html('<span style="color: #ccc;">No icon</span>');
                return;
            }
            
            // Check if it's SVG
            if (iconValue.includes('<svg')) {
                // Render SVG
                previewIcon.html(iconValue);
            } else {
                // Render as text
                previewIcon.html(`<span class="text-icon">${iconValue}</span>`);
            }
            
            console.log('🎨 Icon preview updated:', iconValue.substring(0, 30) + (iconValue.length > 30 ? '...' : ''));
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        console.log('🔧 Dynamic Post Type Filters Admin: DOM Ready');
        console.log('🔧 Wrap elements found:', $('.wrap').length);
        console.log('🔧 Modal elements found:', $('#post-type-filter-modal').length);
        console.log('🔧 Config available:', typeof window.sffc_dynamic_admin !== 'undefined');
        
        // Initialize even if modal doesn't exist yet - it might be created dynamically
        if ($('.wrap').length) {
            console.log('🔧 Initializing Dynamic Post Type Filters Admin');
            window.DynamicPostTypeFiltersAdmin = new DynamicPostTypeFiltersAdmin();
        } else {
            console.log('🔧 No .wrap element found - not initializing');
        }
    });
    
})(jQuery);