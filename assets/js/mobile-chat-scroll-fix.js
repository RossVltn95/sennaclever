/**
 * Mobile Chat Scroll Fix
 * Fixes bouncing and rough scrolling in mobile chat
 * Implements smart scroll with user intent detection
 */

(function($) {
    'use strict';
    
    console.log('[ChatScrollFix] Initializing...');
    
    class ChatScrollFix {
        constructor() {
            this.isUserScrolling = false;
            this.scrollTimeout = null;
            this.lastScrollTop = 0;
            this.scrollThreshold = 100; // px from bottom to consider "at bottom"
            this.isTyping = false;
            this.pendingScroll = false;
            
            this.init();
        }
        
        init() {
            $(document).ready(() => {
                // ONLY apply fixes on mobile
                if (window.innerWidth > 768) {
                    console.log('[ChatScrollFix] Desktop detected, skipping fixes');
                    return;
                }
                
                console.log('[ChatScrollFix] Mobile detected, applying fixes...');
                
                // Override the scrollToBottom methods
                this.overrideScrollMethods();
                
                // Setup scroll listeners
                this.setupScrollListeners();
                
                // Fix CSS for smooth scrolling
                this.applyCSSFixes();
                
                console.log('[ChatScrollFix] Fixes applied');
            });
        }
        
        overrideScrollMethods() {
            // Store reference to original methods if they exist
            if (window.sennaConversational) {
                const originalScrollToBottom = window.sennaConversational.scrollToBottom;
                
                // Override with smart scroll
                window.sennaConversational.scrollToBottom = () => {
                    this.smartScrollToBottom();
                };
                
                // Also override the duplicate method if it exists
                if (window.sennaConversational.constructor.prototype.scrollToBottom) {
                    window.sennaConversational.constructor.prototype.scrollToBottom = () => {
                        this.smartScrollToBottom();
                    };
                }
            }
            
            // Override global scrollToBottom if it exists
            if (window.scrollToBottom) {
                window.scrollToBottom = () => {
                    this.smartScrollToBottom();
                };
            }
            
            // Patch typing methods to use debounced scrolling
            this.patchTypingMethods();
        }
        
        patchTypingMethods() {
            if (window.sennaConversational) {
                const original = window.sennaConversational;
                
                // Override typeMessage if it exists
                if (original.typeMessage) {
                    const originalTypeMessage = original.typeMessage.bind(original);
                    original.typeMessage = (text, element, callback) => {
                        this.isTyping = true;
                        
                        // Wrap the original to control scrolling
                        const wrappedCallback = () => {
                            this.isTyping = false;
                            this.smartScrollToBottom(); // Only scroll once at end
                            if (callback) callback();
                        };
                        
                        // Call original but intercept scrollToBottom calls during typing
                        const tempScroll = original.scrollToBottom;
                        original.scrollToBottom = () => {
                            // Queue scroll but don't execute during typing
                            this.pendingScroll = true;
                        };
                        
                        originalTypeMessage(text, element, wrappedCallback);
                        
                        // Restore after a delay
                        setTimeout(() => {
                            original.scrollToBottom = tempScroll;
                        }, 100);
                    };
                }
                
                // Override typeHTMLMessage similarly
                if (original.typeHTMLMessage) {
                    const originalTypeHTML = original.typeHTMLMessage.bind(original);
                    original.typeHTMLMessage = (html, element) => {
                        this.isTyping = true;
                        
                        // Temporarily disable scrolling during typing
                        const tempScroll = original.scrollToBottom;
                        original.scrollToBottom = () => {
                            this.pendingScroll = true;
                        };
                        
                        originalTypeHTML(html, element);
                        
                        // Restore and scroll once at end
                        setTimeout(() => {
                            original.scrollToBottom = tempScroll;
                            this.isTyping = false;
                            if (this.pendingScroll) {
                                this.smartScrollToBottom();
                                this.pendingScroll = false;
                            }
                        }, 1000); // Wait for typing to complete
                    };
                }
            }
        }
        
        setupScrollListeners() {
            const $messages = $('#senna-messages');
            if (!$messages.length) return;
            
            let scrollEndTimer;
            
            $messages.on('scroll', (e) => {
                const element = e.target;
                const scrollTop = element.scrollTop;
                const scrollHeight = element.scrollHeight;
                const clientHeight = element.clientHeight;
                const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
                
                // Detect user scrolling (not programmatic)
                if (Math.abs(scrollTop - this.lastScrollTop) > 5) {
                    // User is scrolling if they're not at the bottom
                    this.isUserScrolling = distanceFromBottom > this.scrollThreshold;
                    
                    // Clear existing timer
                    clearTimeout(scrollEndTimer);
                    
                    // Set timer to reset flag after scrolling stops
                    scrollEndTimer = setTimeout(() => {
                        // Check if user scrolled to bottom
                        const currentDistance = element.scrollHeight - element.scrollTop - element.clientHeight;
                        if (currentDistance <= 10) {
                            this.isUserScrolling = false;
                            console.log('[ChatScrollFix] User scrolled to bottom, auto-scroll enabled');
                        }
                    }, 150);
                }
                
                this.lastScrollTop = scrollTop;
            });
        }
        
        smartScrollToBottom() {
            // Don't scroll if currently typing
            if (this.isTyping) {
                this.pendingScroll = true;
                return;
            }
            
            // Don't scroll if user has scrolled up
            if (this.isUserScrolling) {
                console.log('[ChatScrollFix] User scrolling detected, skipping auto-scroll');
                return;
            }
            
            const messages = document.getElementById('senna-messages');
            if (!messages) return;
            
            // Check if we're already near bottom
            const distanceFromBottom = messages.scrollHeight - messages.scrollTop - messages.clientHeight;
            
            // Only scroll if we're already near the bottom or it's a new conversation
            if (distanceFromBottom <= this.scrollThreshold || messages.scrollTop === 0) {
                // Use native smooth scrolling (CSS handles the smoothness)
                messages.scrollTo({
                    top: messages.scrollHeight,
                    behavior: 'smooth'
                });
                
                console.log('[ChatScrollFix] Smooth scroll to bottom');
            } else {
                console.log('[ChatScrollFix] Not scrolling - user reading above');
            }
        }
        
        applyCSSFixes() {
            // Add CSS to ensure smooth scrolling works properly
            const style = document.createElement('style');
            style.id = 'chat-scroll-fix-styles';
            style.innerHTML = `
                /* Mobile-only styles */
                @media (max-width: 768px) {
                    /* Smooth scroll behavior for chat - MOBILE ONLY */
                    #senna-messages {
                        scroll-behavior: smooth !important;
                        -webkit-overflow-scrolling: touch !important;
                        overflow-y: auto !important;
                        overscroll-behavior: contain !important;
                    }
                    #senna-messages {
                        max-height: calc(100vh - 120px) !important; /* Reduced from 180px */
                        height: auto !important; /* Let it size naturally */
                        min-height: calc(100vh - 120px) !important;
                    }
                    
                    /* Smooth transitions for message appearance */
                    .sffc-message {
                        animation: messageSlideIn 0.3s ease-out;
                    }
                    
                    @keyframes messageSlideIn {
                        from {
                            opacity: 0;
                            transform: translateY(10px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                    
                    /* Prevent layout shift during typing */
                    .typing-indicator {
                        min-height: 40px;
                    }
                    
                    /* Fix input position - ensure it stays at bottom */
                    body.mobile-interface-v2 .sffc-autocomplete-container {
                        position: fixed !important;
                        bottom: 0 !important;
                        left: 0 !important;
                        right: 0 !important;
                        height: auto !important;
                        max-height: 80px !important;
                        z-index: 100 !important;
                    }
                    
                    /* Adjust messages padding to match input height */
                    body.mobile-interface-v2 #senna-messages {
                        padding-bottom: 80px !important; /* Match input container height */
                    }
                    
                    /* Ensure quick actions aren't covered */
                    body.mobile-interface-v2 .quick-actions-trigger {
                        bottom: 90px !important; /* Above input field */
                        z-index: 99 !important;
                    }
                    
                    /* Hardware acceleration for smooth scrolling - MOBILE ONLY */
                    .sffc-senna-conversation,
                    #senna-messages {
                        transform: translateZ(0);
                        -webkit-transform: translateZ(0);
                        will-change: scroll-position;
                    }
                }
            `;
            
            // Remove existing style if present
            const existingStyle = document.getElementById('chat-scroll-fix-styles');
            if (existingStyle) {
                existingStyle.remove();
            }
            
            document.head.appendChild(style);
        }
        
        // Public method to manually scroll
        forceScrollToBottom() {
            const messages = document.getElementById('senna-messages');
            if (messages) {
                messages.scrollTop = messages.scrollHeight;
            }
        }
        
        // Public method to reset user scrolling flag
        resetUserScroll() {
            this.isUserScrolling = false;
        }
    }
    
    // Initialize the fix
    window.chatScrollFix = new ChatScrollFix();
    
    // Expose methods globally for debugging
    window.forceScrollToBottom = () => window.chatScrollFix.forceScrollToBottom();
    window.resetUserScroll = () => window.chatScrollFix.resetUserScroll();
    
    console.log('[ChatScrollFix] Script loaded');
    
})(jQuery);