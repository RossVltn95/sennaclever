/**
 * SFFC Messaging System Fix
 * Emergency patch to ensure messaging works
 */

(function($) {
    'use strict';
    
    // Wait for document ready
    $(document).ready(function() {
        console.log('SFFC Messaging Fix: Initializing...');
        
        // Check if main interface or opportunities wrapper exists
        if ($('.sffc-main-wrapper').length === 0 && $('.sffc-opportunities-wrapper').length === 0) {
            console.log('SFFC: No relevant interface found on this page');
            return;
        }
        
        // Ensure SFFC object exists
        if (typeof window.SFFC === 'undefined') {
            console.error('SFFC: Main object not initialized');
            return;
        }
        
        // Fix any initialization issues
        setTimeout(function() {
            // Ensure config is properly set
            if (window.SFFC && window.SFFC.config) {
                if (!window.SFFC.config.ajaxUrl) {
                    window.SFFC.config.ajaxUrl = '/wp-admin/admin-ajax.php';
                    console.warn('SFFC: Fixed missing AJAX URL');
                }
                
                if (!window.SFFC.config.nonce && window.sffc_frontend) {
                    window.SFFC.config.nonce = window.sffc_frontend.nonce || window.sffc_frontend.public_nonce || '';
                    console.warn('SFFC: Fixed missing nonce');
                }
            }
            
            // Re-bind critical events if needed
            if ($('#sffc-start-btn').length && !$._data($('#sffc-start-btn')[0], 'events')) {
                console.warn('SFFC: Re-binding start button');
                $('#sffc-start-btn').on('click', function() {
                    if (window.SFFC && window.SFFC.handleSearchSubmit) {
                        window.SFFC.handleSearchSubmit();
                    }
                });
            }
            
            if ($('#sffc-search-input').length && !$._data($('#sffc-search-input')[0], 'events')) {
                console.warn('SFFC: Re-binding search input');
                $('#sffc-search-input').on('keypress', function(e) {
                    if (e.which === 13 && window.SFFC && window.SFFC.handleSearchSubmit) {
                        window.SFFC.handleSearchSubmit();
                    }
                });
            }
            
            console.log('SFFC Messaging Fix: Complete');
        }, 100);
    });
    
    // Global error handler for AJAX
    $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
        if (ajaxSettings.url && ajaxSettings.url.includes('admin-ajax.php')) {
            // Only log non-error-logging requests to prevent infinite loop
            var requestData = ajaxSettings.data;
            var action = 'Unknown';
            
            // Parse action from data
            if (typeof requestData === 'string') {
                var match = requestData.match(/action=([^&]+)/);
                if (match) {
                    action = match[1];
                }
            } else if (requestData && requestData.action) {
                action = requestData.action;
            }
            
            // Skip logging for error logging requests to prevent loop
            if (action === 'sffc_log_error') {
                return;
            }
            
            console.error('SFFC AJAX Error:', {
                status: jqXHR.status,
                statusText: jqXHR.statusText,
                responseText: jqXHR.responseText ? jqXHR.responseText.substring(0, 200) : 'No response',
                action: action
            });
            
            // Handle 400 errors specifically
            if (jqXHR.status === 400) {
                var errorData;
                try {
                    errorData = JSON.parse(jqXHR.responseText);
                } catch (e) {
                    errorData = { message: 'Connection error' };
                }
                
                // Show user-friendly error
                if ($('#sffc-messages').length) {
                    var errorHtml = '<div class="sffc-message sffc-error-message">' +
                        '<strong>Connection Error:</strong> ' + 
                        (errorData.data && errorData.data.message ? errorData.data.message : 'Please refresh the page and try again.') +
                        '</div>';
                    $('#sffc-messages').append(errorHtml);
                }
                
                // Re-enable input
                $('#sffc-message-input, #sffc-send-btn').prop('disabled', false);
            }
        }
    });
    
})(jQuery);