/**
 * Typography and UI Handler
 * Manages font settings and message scrolling
 */

(function($) {
    'use strict';
    
    // Apply typography settings on load
    function applyTypographySettings() {
        const container = $('.sffc-chat-container, .sffc-premium-interface');
        if (!container.length) return;
        
        // Get settings from data attributes or localStorage
        const settings = {
            fontFamily: container.data('font-family') || sffcTypography?.fontFamily || 'system',
            fontSize: container.data('font-size') || sffcTypography?.fontSize || '16px',
            lineHeight: container.data('line-height') || sffcTypography?.lineHeight || '1.6',
            disableShadows: container.data('disable-shadows') || sffcTypography?.disableShadows || false,
            disableSmoothing: container.data('disable-smoothing') || sffcTypography?.disableSmoothing || false
        };
        
        // Remove all font classes
        container.removeClass(function(index, className) {
            return (className.match(/sffc-font-\S+/g) || []).join(' ');
        });
        
        // Apply font family
        container.addClass('sffc-font-' + settings.fontFamily);
        
        // Apply font size
        const sizeNum = settings.fontSize.replace('px', '');
        container.addClass('sffc-font-' + sizeNum);
        
        // Apply line height
        const lineNum = settings.lineHeight.replace('.', '');
        container.addClass('sffc-line-' + lineNum);
        
        // Apply effect settings
        if (settings.disableShadows) {
            container.addClass('sffc-no-shadows');
        }
        
        if (settings.disableSmoothing) {
            container.addClass('sffc-no-smoothing');
        }
    }
    
    // Auto-scroll to new messages
    function scrollToMessage(messageElement) {
        if (!messageElement || !messageElement.length) return;
        
        // Find the message text element
        const messageText = messageElement.find('.sffc-message-text');
        const container = messageElement.closest('.sffc-messages-container');
        
        if (messageText.length && container.length) {
            // Scroll the message text to top of container
            const textTop = messageText.offset().top;
            const containerTop = container.offset().top;
            const scrollTop = container.scrollTop() + (textTop - containerTop - 20);
            
            container.animate({
                scrollTop: scrollTop
            }, 200);
        }
    }
    
    // Handle visual card loading states
    function handleVisualLoading(visualCard) {
        if (!visualCard || !visualCard.length) return;
        
        // Remove loading indicator when content is present
        if (visualCard.find('.sffc-visual-content').children().length > 0) {
            visualCard.addClass('loaded sffc-visual-loaded');
            visualCard.find('.sffc-visual-loading').fadeOut(200);
        }
    }
    
    // Monitor for new messages
    const messageObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if ($(node).hasClass('sffc-message')) {
                    // Scroll to new message
                    setTimeout(() => scrollToMessage($(node)), 50);
                    
                    // Check for visual cards
                    const visualCard = $(node).find('.sffc-visual-card');
                    if (visualCard.length) {
                        setTimeout(() => handleVisualLoading(visualCard), 100);
                    }
                }
            });
        });
    });
    
    // Initialize on document ready
    $(document).ready(function() {
        // Apply typography settings
        applyTypographySettings();
        
        // Start observing for new messages
        const messagesContainer = $('.sffc-messages-container');
        if (messagesContainer.length) {
            messageObserver.observe(messagesContainer[0], {
                childList: true,
                subtree: true
            });
        }
        
        // Listen for settings changes
        $(document).on('sffc:settings-updated', function() {
            applyTypographySettings();
        });
        
        // Fix visual card loading states on existing cards
        $('.sffc-visual-card').each(function() {
            handleVisualLoading($(this));
        });
    });
    
    // Handle AJAX responses for visual cards
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.includes('sffc_')) {
            // Check all visual cards after AJAX
            setTimeout(function() {
                $('.sffc-visual-card').each(function() {
                    handleVisualLoading($(this));
                });
            }, 100);
        }
    });
    
})(jQuery);