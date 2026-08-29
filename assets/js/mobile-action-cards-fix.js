/**
 * Mobile Action Cards Fix - Priority Version
 * Ensures this loads last and takes precedence over other handlers
 * Namespace: mobileActionFix to prevent conflicts
 */

(function($) {
    'use strict';
    
    console.log('[MobileActionFix] Initializing...');
    
    // Wait for all scripts to load
    let initAttempts = 0;
    const maxAttempts = 20; // 10 seconds max
    
    function initWhenReady() {
        // CRITICAL: Only run on mobile
        if (window.innerWidth > 768) {
            console.log('[MobileActionFix] Desktop detected, not initializing');
            return;
        }
        
        initAttempts++;
        
        // Check if required dependencies are loaded
        const dependenciesLoaded = 
            typeof jQuery !== 'undefined' &&
            $('.pe-main-filters').length > 0 || 
            $('.action-cards-container').length > 0 ||
            initAttempts > 10; // After 5 seconds, try anyway
        
        if (dependenciesLoaded) {
            console.log('[MobileActionFix] Dependencies ready, initializing...');
            initializeFix();
        } else if (initAttempts < maxAttempts) {
            console.log('[MobileActionFix] Waiting for dependencies... attempt', initAttempts);
            setTimeout(initWhenReady, 500);
        } else {
            console.log('[MobileActionFix] Max attempts reached, forcing init');
            initializeFix();
        }
    }
    
    function initializeFix() {
        // Remove any existing handlers first to prevent conflicts
        $(document).off('click.mobileActionFix');
        
        // Use high-priority namespace to ensure our handlers run
        bindEventHandlers();
        
        // Re-bind every 2 seconds to ensure we stay on top (MOBILE ONLY)
        setInterval(function() {
            if (window.innerWidth <= 768 && !window.mobileActionFixActive) {
                console.log('[MobileActionFix] Re-binding handlers...');
                bindEventHandlers();
            }
        }, 2000);
        
        console.log('[MobileActionFix] Initialized successfully');
    }
    
    function bindEventHandlers() {
        // ONLY bind on mobile devices
        if (window.innerWidth > 768) {
            console.log('[MobileActionFix] Desktop detected, skipping event binding');
            return;
        }
        
        // Mark as active
        window.mobileActionFixActive = true;
        
        // Remove and re-add to ensure we're last in the event chain
        $(document).off('click.mobileActionFix');
        
        // Handler 1: Action trigger buttons (MOBILE ONLY)
        $(document).on('click.mobileActionFix', '.action-trigger-btn', function(e) {
            // Double-check we're on mobile
            if (window.innerWidth > 768) return;
            
            console.log('[MobileActionFix] Action button clicked!');
            e.preventDefault();
            e.stopImmediatePropagation(); // Stop other handlers
            
            const $btn = $(this);
            handleActionClick($btn);
            return false; // Extra prevention
        });
        
        // Handler 2: Ask MENA Careers buttons on action cards (MOBILE ONLY)
        $(document).on('click.mobileActionFix', '.action-card .ask-senna-btn', function(e) {
            // Double-check we're on mobile
            if (window.innerWidth > 768) return;
            
            console.log('[MobileActionFix] Ask MENA Careers button clicked!');
            e.preventDefault();
            e.stopImmediatePropagation();
            
            const $btn = $(this);
            handleActionClick($btn);
            return false;
        });
        
        // Handler 3: Action card body clicks (MOBILE ONLY)
        $(document).on('click.mobileActionFix', '.question-card.action-card', function(e) {
            // Double-check we're on mobile
            if (window.innerWidth > 768) return;
            
            // Only if not clicking a button
            if (!$(e.target).closest('.action-trigger-btn, .ask-senna-btn').length) {
                console.log('[MobileActionFix] Card body clicked!');
                e.preventDefault();
                e.stopImmediatePropagation();
                
                const $card = $(this);
                const $btn = $card.find('.action-trigger-btn, .ask-senna-btn').first();
                
                if ($btn.length) {
                    handleActionClick($btn);
                }
                return false;
            }
        });
        
        // Capture phase listener - MOBILE ONLY
        document.addEventListener('click', function(e) {
            // CRITICAL: Only capture on mobile!
            if (window.innerWidth > 768) return;
            
            const target = e.target;
            
            // Check if it's our target element
            if (target.matches('.action-trigger-btn') || 
                target.closest('.action-trigger-btn') ||
                (target.matches('.ask-senna-btn') && target.closest('.action-card'))) {
                
                console.log('[MobileActionFix] Captured at document level!');
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(target.closest('.action-trigger-btn, .ask-senna-btn'));
                if ($btn.length) {
                    handleActionClick($btn);
                }
            }
        }, true); // Use capture phase
        
        window.mobileActionFixActive = false; // Reset flag
    }
    
    function handleActionClick($btn) {
        // Get prompt from multiple possible attributes
        let prompt = $btn.attr('data-prompt') || 
                     $btn.data('prompt') || 
                     $btn.attr('data-action-prompt');
        
        // If no prompt on button, try parent card
        if (!prompt) {
            const $card = $btn.closest('.action-card');
            prompt = $card.attr('data-prompt') || $card.data('prompt');
        }
        
        // Build prompt from card content if still no prompt
        if (!prompt) {
            const $card = $btn.closest('.action-card');
            const title = $card.find('.question-title').text().trim();
            const preview = $card.find('.question-preview').text().trim();
            
            if (title) {
                prompt = title + (preview ? '. ' + preview : '');
            }
        }
        
        if (prompt) {
            console.log('[MobileActionFix] Prompt found:', prompt.substring(0, 50) + '...');
            navigateToChatWithPrompt(prompt);
        } else {
            console.error('[MobileActionFix] No prompt found!');
            // Use a default prompt as fallback
            navigateToChatWithPrompt('Tell me more about private equity opportunities');
        }
    }
    
    function navigateToChatWithPrompt(prompt) {
        console.log('[MobileActionFix] Navigating to chat...');
        
        // Only process on mobile
        if (window.innerWidth > 768) {
            console.log('[MobileActionFix] Not mobile, skipping navigation');
            return;
        }
        
        // Method 1: Use mobileInterfaceV2 if available
        if (window.mobileInterfaceV2 && typeof window.mobileInterfaceV2.switchMode === 'function') {
            console.log('[MobileActionFix] Using mobileInterfaceV2.switchMode');
            try {
                window.mobileInterfaceV2.switchMode('chat');
            } catch(e) {
                console.error('[MobileActionFix] switchMode failed:', e);
            }
        } else {
            // Method 2: Manual DOM manipulation
            console.log('[MobileActionFix] Manual mode switch');
            
            // Hide browse mode elements
            $('.pe-filter-sidebar, .pe-main-filters, .action-cards-container').each(function() {
                $(this).hide().css('display', 'none');
            });
            
            // Show chat elements
            $('.sffc-senna-conversation, .mobile-senna-conversation').each(function() {
                $(this).show().css({
                    'display': 'flex',
                    'visibility': 'visible',
                    'opacity': '1'
                });
            });
            
            // Update mode pills
            $('.mode-pill').removeClass('active');
            $('.mode-pill[data-mode="chat"]').addClass('active');
            
            // Update body classes
            $('body').addClass('mobile-chat-active').removeClass('mobile-browse-active');
        }
        
        // Send prompt after UI switch
        setTimeout(function() {
            sendPromptToChat(prompt);
        }, 500);
    }
    
    function sendPromptToChat(prompt) {
        console.log('[MobileActionFix] Sending prompt...');
        let sent = false;
        
        // Method 1: Direct SennaChat.send (highest priority)
        if (!sent && window.SennaChat && typeof window.SennaChat.send === 'function') {
            try {
                console.log('[MobileActionFix] Using SennaChat.send()');
                window.SennaChat.send(prompt);
                sent = true;
            } catch(e) {
                console.error('[MobileActionFix] SennaChat.send failed:', e);
            }
        }
        
        // Method 2: sennaConversational.handleUserInput
        if (!sent && window.sennaConversational && typeof window.sennaConversational.handleUserInput === 'function') {
            try {
                console.log('[MobileActionFix] Using handleUserInput()');
                window.sennaConversational.handleUserInput(prompt);
                sent = true;
            } catch(e) {
                console.error('[MobileActionFix] handleUserInput failed:', e);
            }
        }
        
        // Method 3: Set input value and trigger send
        if (!sent) {
            const $input = $('#senna-input');
            const $sendBtn = $('#senna-send, .sffc-send-btn, button[type="submit"]').first();
            
            if ($input.length && $sendBtn.length) {
                console.log('[MobileActionFix] Using input + button method');
                
                // Set value
                $input.val(prompt).trigger('change').trigger('input');
                
                // Wait a bit then click send
                setTimeout(function() {
                    $sendBtn.trigger('click');
                    // Double-click as fallback
                    setTimeout(function() {
                        if ($input.val() === prompt) {
                            $sendBtn.trigger('click');
                        }
                    }, 200);
                }, 100);
                
                sent = true;
            }
        }
        
        // Method 4: Direct message addition as last resort
        if (!sent && window.sennaConversational) {
            try {
                console.log('[MobileActionFix] Adding message directly');
                window.sennaConversational.addUserMessage(prompt);
                if (window.sennaConversational.processUserIntent) {
                    window.sennaConversational.processUserIntent(prompt);
                }
                sent = true;
            } catch(e) {
                console.error('[MobileActionFix] Direct message failed:', e);
            }
        }
        
        if (sent) {
            console.log('[MobileActionFix] Prompt sent successfully!');
        } else {
            console.error('[MobileActionFix] Failed to send prompt');
        }
    }
    
    // Start initialization
    $(document).ready(function() {
        // Delay slightly to let other scripts initialize first
        setTimeout(initWhenReady, 1000);
    });
    
    // Also try on window load
    $(window).on('load', function() {
        setTimeout(initWhenReady, 1500);
    });
    
})(jQuery);