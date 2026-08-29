/**
 * MENA Careers Input Field Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initAdminInterface();
        loadInitialPreview();
    });
    
    /**
     * Initialize admin interface
     */
    function initAdminInterface() {
        // Preview button
        $('#sffc-preview-input').on('click', function(e) {
            e.preventDefault();
            updatePreview();
        });
        
        // Reset defaults button
        $('#sffc-reset-defaults').on('click', function(e) {
            e.preventDefault();
            resetToDefaults();
        });
        
        // Auto-update preview when settings change
        $('#sffc-input-settings-form input, #sffc-input-settings-form select').on('change input', 
            debounce(updatePreview, 500)
        );
        
        // Copy shortcode functionality
        $(document).on('click', '.sffc-code-block', function() {
            selectText(this);
            
            // Try to copy to clipboard
            try {
                document.execCommand('copy');
                showNotice('Shortcode copied to clipboard!', 'success');
            } catch (err) {
                console.log('Copy failed:', err);
            }
        });
        
        // Form validation
        $('#sffc-input-settings-form').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                showNotice('Please correct the errors before saving.', 'error');
            }
        });
    }
    
    /**
     * Load initial preview
     */
    function loadInitialPreview() {
        updatePreview();
    }
    
    /**
     * Update preview
     */
    function updatePreview() {
        const settings = getFormSettings();
        
        $('#sffc-input-preview').html('<div class="sffc-loading">Loading preview...</div>');
        
        $.ajax({
            url: sffc_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sffc_preview_input_field',
                settings: settings,
                nonce: sffc_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#sffc-input-preview').html(response.data.html);
                } else {
                    $('#sffc-input-preview').html('<div class="error">Preview failed: ' + 
                        (response.data?.message || 'Unknown error') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#sffc-input-preview').html('<div class="error">Preview failed: ' + error + '</div>');
            }
        });
    }
    
    /**
     * Get form settings
     */
    function getFormSettings() {
        const settings = {};
        
        $('#sffc-input-settings-form input, #sffc-input-settings-form select').each(function() {
            const $field = $(this);
            const name = $field.attr('name');
            
            if (name && name.includes('sffc_senna_input_settings[')) {
                const fieldName = name.replace('sffc_senna_input_settings[', '').replace(']', '');
                
                if ($field.attr('type') === 'checkbox') {
                    settings[fieldName] = $field.is(':checked') ? 'true' : 'false';
                } else {
                    settings[fieldName] = $field.val();
                }
            }
        });
        
        return settings;
    }
    
    /**
     * Reset to defaults
     */
    function resetToDefaults() {
        if (!confirm('Are you sure you want to reset all settings to their default values?')) {
            return;
        }
        
        const defaults = {
            placeholder: 'Ask MENA Careers about this content...',
            button_text: 'Ask MENA Careers',
            redirect_url: '',
            theme: 'default',
            width: '100%',
            custom_class: '',
            include_content: true,
            include_title: true,
            auto_focus: false
        };
        
        // Update form fields
        $.each(defaults, function(field, value) {
            const $field = $('[name="sffc_senna_input_settings[' + field + ']"]');
            
            if ($field.attr('type') === 'checkbox') {
                $field.prop('checked', value === true);
            } else {
                $field.val(value);
            }
        });
        
        // Update preview
        updatePreview();
        
        showNotice('Settings reset to defaults. Don\'t forget to save!', 'info');
    }
    
    /**
     * Validate form
     */
    function validateForm() {
        let isValid = true;
        
        // Clear previous errors
        $('.form-table .error').removeClass('error');
        
        // Validate placeholder
        const placeholder = $('#placeholder').val().trim();
        if (placeholder.length === 0) {
            $('#placeholder').closest('tr').addClass('error');
            isValid = false;
        }
        
        // Validate button text
        const buttonText = $('#button_text').val().trim();
        if (buttonText.length === 0) {
            $('#button_text').closest('tr').addClass('error');
            isValid = false;
        }
        
        // Validate redirect URL (if provided)
        const redirectUrl = $('#redirect_url').val().trim();
        if (redirectUrl.length > 0 && !isValidUrl(redirectUrl)) {
            $('#redirect_url').closest('tr').addClass('error');
            isValid = false;
        }
        
        // Validate width
        const width = $('#width').val().trim();
        if (width.length === 0 || !isValidCssValue(width)) {
            $('#width').closest('tr').addClass('error');
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Check if URL is valid
     */
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            // Check for relative URLs
            return string.startsWith('/') && string.length > 1;
        }
    }
    
    /**
     * Check if CSS value is valid
     */
    function isValidCssValue(value) {
        // Allow percentages, pixels, em, rem, auto, etc.
        return /^(auto|\d+(%|px|em|rem|vw|vh))$/.test(value);
    }
    
    /**
     * Show admin notice
     */
    function showNotice(message, type = 'info') {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
        
        // Add dismiss functionality
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut();
        });
    }
    
    /**
     * Select text in element
     */
    function selectText(element) {
        const range = document.createRange();
        range.selectNodeContents(element);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
    
    /**
     * Debounce function
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Enhanced UX features
     */
    
    // Add tooltips to help icons
    $(document).on('mouseenter', '.description', function() {
        $(this).attr('title', $(this).text());
    });
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#sffc-input-settings-form').submit();
        }
        
        // Ctrl/Cmd + P to preview
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            $('#sffc-preview-input').click();
        }
    });
    
    // Add loading states
    function setButtonLoading($button, isLoading) {
        if (isLoading) {
            $button.prop('disabled', true).text('Loading...');
        } else {
            $button.prop('disabled', false).text($button.data('original-text') || $button.text());
        }
    }
    
    // Store original button texts
    $('.button').each(function() {
        $(this).data('original-text', $(this).text());
    });
    
    // Add form change detection
    let formChanged = false;
    $('#sffc-input-settings-form input, #sffc-input-settings-form select').on('change', function() {
        formChanged = true;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    $('#sffc-input-settings-form').on('submit', function() {
        formChanged = false;
    });
    
})(jQuery);