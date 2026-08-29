/**
 * Custom Intelligence Admin JavaScript
 * Admin interface for managing custom intelligence filters and cards
 * 
 * @package SkillFarmFinance
 * @since 10.20.0
 */

(function($) {
    'use strict';
    
    class CustomIntelligenceAdmin {
        constructor() {
            this.config = window.sffc_custom_admin || {};
            this.isEditing = false;
            this.currentEditId = null;
            
            console.log('🔧 Custom Intelligence Admin: Initializing...');
            
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initColorPickers();
            this.initModals();
        }
        
        bindEvents() {
            // Filter Type Management
            $(document).on('click', '#add-filter-type, #add-first-filter', () => {
                this.openFilterTypeModal();
            });
            
            $(document).on('click', '.edit-filter', (e) => {
                const filterKey = $(e.target).data('filter-key');
                this.editFilterType(filterKey);
            });
            
            $(document).on('click', '.delete-filter', (e) => {
                const filterKey = $(e.target).data('filter-key');
                this.deleteFilterType(filterKey);
            });
            
            $(document).on('submit', '#filter-type-form', (e) => {
                e.preventDefault();
                this.saveFilterType();
            });
            
            // Intelligence Card Management
            $(document).on('click', '#add-intelligence-card, #add-first-card', () => {
                this.openCardModal();
            });
            
            $(document).on('click', '.edit-card', (e) => {
                const cardId = $(e.target).data('card-id');
                this.editCard(cardId);
            });
            
            $(document).on('click', '.delete-card', (e) => {
                const cardId = $(e.target).data('card-id');
                this.deleteCard(cardId);
            });
            
            $(document).on('submit', '#intelligence-card-form', (e) => {
                e.preventDefault();
                this.saveCard();
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
        }
        
        initColorPickers() {
            if ($.fn.wpColorPicker) {
                $('.color-picker').wpColorPicker();
            }
        }
        
        initModals() {
            // Ensure modals are hidden initially
            $('.sffc-modal').hide();
        }
        
        // Filter Type Management
        openFilterTypeModal(filterKey = null) {
            this.isEditing = !!filterKey;
            this.currentEditId = filterKey;
            
            const modal = $('#filter-type-modal');
            const title = this.isEditing ? 'Edit Filter Type' : 'Add Filter Type';
            
            modal.find('#modal-title').text(title);
            
            if (this.isEditing) {
                this.populateFilterTypeForm(filterKey);
            } else {
                this.resetFilterTypeForm();
            }
            
            modal.show();
        }
        
        populateFilterTypeForm(filterKey) {
            // Get data from the table row
            const row = $(`tr[data-filter-key="${filterKey}"]`);
            const icon = row.find('.filter-icon').text();
            const label = row.find('td:nth-child(2)').text();
            const color = row.find('td:nth-child(3)').text().trim();
            const priority = row.find('td:nth-child(4)').text();
            const isActive = row.find('.status-badge').hasClass('active');
            
            $('#filter-key').val(filterKey).prop('readonly', true);
            $('#filter-label').val(label);
            $('#filter-icon').val(icon);
            $('#filter-color').val(color);
            $('#filter-priority').val(priority);
            $('#filter-active').prop('checked', isActive);
            
            // Update color picker if available
            if ($.fn.wpColorPicker) {
                $('#filter-color').wpColorPicker('color', color);
            }
        }
        
        resetFilterTypeForm() {
            $('#filter-type-form')[0].reset();
            $('#filter-key').prop('readonly', false);
            $('#filter-priority').val('10');
            $('#filter-active').prop('checked', true);
            
            if ($.fn.wpColorPicker) {
                $('#filter-color').wpColorPicker('color', '#007cba');
            }
        }
        
        saveFilterType() {
            const formData = this.getFilterTypeFormData();
            
            if (!this.validateFilterTypeForm(formData)) {
                return;
            }
            
            this.showSaving();
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_save_custom_filter',
                    nonce: this.config.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(this.config.strings.save_success);
                        this.closeModals();
                        this.refreshPage();
                    } else {
                        this.showError(response.data || this.config.strings.save_error);
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
        
        deleteFilterType(filterKey) {
            if (!confirm(this.config.strings.confirm_delete)) {
                return;
            }
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_delete_custom_filter',
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
        
        getFilterTypeFormData() {
            return {
                filter_key: $('#filter-key').val().trim(),
                label: $('#filter-label').val().trim(),
                icon: $('#filter-icon').val().trim(),
                color: $('#filter-color').val(),
                priority: parseInt($('#filter-priority').val()) || 10,
                active: $('#filter-active').prop('checked')
            };
        }
        
        validateFilterTypeForm(data) {
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
            
            return true;
        }
        
        // Intelligence Card Management
        openCardModal(cardId = null) {
            this.isEditing = !!cardId;
            this.currentEditId = cardId;
            
            const modal = $('#intelligence-card-modal');
            const title = this.isEditing ? 'Edit Intelligence Card' : 'Add Intelligence Card';
            
            modal.find('#card-modal-title').text(title);
            
            if (this.isEditing) {
                this.populateCardForm(cardId);
            } else {
                this.resetCardForm();
            }
            
            modal.show();
        }
        
        populateCardForm(cardId) {
            // Get data from the card preview
            const cardPreview = $(`.card-preview[data-card-id="${cardId}"]`);
            const title = cardPreview.find('h4').text();
            const summary = cardPreview.find('p').text();
            const filterTypeBadge = cardPreview.find('.filter-type-badge').text();
            const isActive = cardPreview.find('.status-badge').hasClass('active');
            
            $('#card-id').val(cardId);
            $('#card-title').val(title);
            $('#card-summary').val(summary);
            $('#card-active').prop('checked', isActive);
            
            // Try to match filter type
            $('#card-filter-type option').each(function() {
                if ($(this).text() === filterTypeBadge) {
                    $(this).prop('selected', true);
                }
            });
        }
        
        resetCardForm() {
            $('#intelligence-card-form')[0].reset();
            $('#card-id').val('');
            $('#card-active').prop('checked', true);
            $('#card-time-ago').val('Recent');
        }
        
        saveCard() {
            const formData = this.getCardFormData();
            
            if (!this.validateCardForm(formData)) {
                return;
            }
            
            this.showSaving();
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_save_custom_card',
                    nonce: this.config.nonce,
                    ...formData
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Card saved successfully');
                        this.closeModals();
                        this.refreshPage();
                    } else {
                        this.showError(response.data || 'Error saving card');
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
        
        deleteCard(cardId) {
            if (!confirm(this.config.strings.confirm_delete)) {
                return;
            }
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_delete_custom_card',
                    nonce: this.config.nonce,
                    card_id: cardId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Card deleted successfully');
                        $(`.card-preview[data-card-id="${cardId}"]`).fadeOut(() => {
                            $(this).remove();
                        });
                    } else {
                        this.showError(response.data || 'Error deleting card');
                    }
                },
                error: () => {
                    this.showError('Network error occurred');
                }
            });
        }
        
        getCardFormData() {
            return {
                card_id: $('#card-id').val(),
                title: $('#card-title').val().trim(),
                summary: $('#card-summary').val().trim(),
                filter_type: $('#card-filter-type').val(),
                category: $('#card-category').val().trim(),
                metric_value: $('#card-metric-value').val().trim(),
                metric_change: $('#card-metric-change').val().trim(),
                ai_insight: $('#card-ai-insight').val().trim(),
                time_ago: $('#card-time-ago').val().trim(),
                active: $('#card-active').prop('checked')
            };
        }
        
        validateCardForm(data) {
            if (!data.title) {
                this.showError('Title is required');
                return false;
            }
            
            if (!data.summary) {
                this.showError('Summary is required');
                return false;
            }
            
            if (!data.filter_type) {
                this.showError('Filter type is required');
                return false;
            }
            
            return true;
        }
        
        // UI Helper Methods
        closeModals() {
            $('.sffc-modal').hide();
            this.isEditing = false;
            this.currentEditId = null;
        }
        
        showSaving() {
            $('button[type="submit"]').prop('disabled', true).text('Saving...');
        }
        
        hideSaving() {
            $('button[type="submit"]').prop('disabled', false);
            $('#filter-type-form button[type="submit"]').text('Save Filter Type');
            $('#intelligence-card-form button[type="submit"]').text('Save Intelligence Card');
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
        
        editFilterType(filterKey) {
            this.openFilterTypeModal(filterKey);
        }
        
        editCard(cardId) {
            this.openCardModal(cardId);
        }
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.wrap').length && ($('#filter-type-modal').length || $('#intelligence-card-modal').length)) {
            window.CustomIntelligenceAdmin = new CustomIntelligenceAdmin();
        }
    });
    
})(jQuery);