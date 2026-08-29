/**
 * Auto Scroll to New Messages
 * Automatically scrolls to show the first sentence of new messages
 */

(function($) {
    'use strict';
    
    class MessageAutoScroller {
        constructor() {
            this.init();
            this.lastMessageCount = 0;
        }
        
        init() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        setup() {
            // Watch for new messages
            this.observeMessages();
            
            // Also listen for jQuery events if available
            if (typeof jQuery !== 'undefined') {
                $(document).on('messageAdded senna:messageReceived', () => {
                    this.scrollToLatestMessage();
                });
            }
        }
        
        /**
         * Observe DOM for new messages
         */
        observeMessages() {
            // Multiple possible message containers
            const containers = [
                '.senna-messages',
                '.sffc-senna-conversation',
                '.sffc-conversational-view',
                '#senna-messages',
                '.ultimate-messages'
            ];
            
            containers.forEach(selector => {
                const container = document.querySelector(selector);
                if (!container) return;
                
                const observer = new MutationObserver((mutations) => {
                    let newMessageAdded = false;
                    
                    mutations.forEach(mutation => {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === 1) {
                                // Check if it's a message element
                                const isMessage = node.classList && (
                                    node.classList.contains('sffc-message') ||
                                    node.classList.contains('senna-message') ||
                                    node.classList.contains('sffc-message-senna') ||
                                    node.classList.contains('sffc-message-user') ||
                                    node.classList.contains('ultimate-msg')
                                );
                                
                                if (isMessage) {
                                    newMessageAdded = true;
                                }
                                
                                // Also check children for messages
                                if (node.querySelector) {
                                    const messages = node.querySelectorAll('.sffc-message, .senna-message, .sffc-message-senna, .sffc-message-user');
                                    if (messages.length > 0) {
                                        newMessageAdded = true;
                                    }
                                }
                            }
                        });
                    });
                    
                    if (newMessageAdded) {
                        // Small delay to ensure DOM is fully updated
                        setTimeout(() => {
                            this.scrollToLatestMessage();
                        }, 100);
                    }
                });
                
                observer.observe(container, {
                    childList: true,
                    subtree: true
                });
            });
        }
        
        /**
         * Scroll to the latest message
         */
        scrollToLatestMessage() {
            // Find all messages
            const messageSelectors = [
                '.sffc-message-senna',
                '.sffc-message-user',
                '.senna-message',
                '.sffc-message',
                '.ultimate-msg'
            ];
            
            let allMessages = [];
            messageSelectors.forEach(selector => {
                const messages = document.querySelectorAll(selector);
                allMessages = allMessages.concat(Array.from(messages));
            });
            
            if (allMessages.length === 0) return;
            
            // Get the last message
            const lastMessage = allMessages[allMessages.length - 1];
            
            // Look for senna-avatar within the message to scroll to the start
            let scrollTarget = lastMessage;
            const sennaAvatar = lastMessage.querySelector('.senna-avatar');
            if (sennaAvatar) {
                scrollTarget = sennaAvatar;
            }
            
            // Scroll to show the message with some offset from top
            this.scrollToElement(scrollTarget);
        }
        
        /**
         * Smooth scroll to element
         */
        scrollToElement(element) {
            if (!element) return;
            
            // Get the position of the element
            const rect = element.getBoundingClientRect();
            const absoluteTop = window.pageYOffset + rect.top;
            
            // Calculate scroll position - show message with some padding from top
            const offset = 100; // Pixels from top of viewport
            const scrollTo = absoluteTop - offset;
            
            // Desktop scroll
            window.scrollTo({
                top: scrollTo,
                behavior: 'smooth'
            });
            
            // Alternative method for specific containers
            const scrollableContainers = [
                '.sffc-conversational-view',
                '.senna-messages',
                '.sffc-senna-conversation'
            ];
            
            scrollableContainers.forEach(selector => {
                const container = document.querySelector(selector);
                if (container && container.contains(element)) {
                    // If element is in a scrollable container, scroll container too
                    const containerRect = container.getBoundingClientRect();
                    const elementRect = element.getBoundingClientRect();
                    const relativeTop = elementRect.top - containerRect.top + container.scrollTop;
                    
                    container.scrollTo({
                        top: relativeTop - 50,
                        behavior: 'smooth'
                    });
                }
            });
            
            // Add a subtle highlight effect to the new message
            element.style.transition = 'background-color 0.5s ease';
            element.style.backgroundColor = 'rgba(201, 169, 97, 0.05)';
            setTimeout(() => {
                element.style.backgroundColor = '';
            }, 2000);
        }
        
        /**
         * Scroll to bottom of conversation
         */
        scrollToBottom() {
            const containers = [
                '.senna-messages',
                '.sffc-conversational-view',
                '.sffc-senna-conversation'
            ];
            
            containers.forEach(selector => {
                const container = document.querySelector(selector);
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
            
            // Also scroll window to bottom area
            window.scrollTo({
                top: document.documentElement.scrollHeight,
                behavior: 'smooth'
            });
        }
    }
    
    // Initialize
    const messageScroller = new MessageAutoScroller();
    
    // Make available globally
    window.messageAutoScroller = messageScroller;
    
    // Hook into existing message systems
    if (typeof jQuery !== 'undefined') {
        // Listen for MENA Careers chat events
        $(document).on('senna:message:added', function() {
            messageScroller.scrollToLatestMessage();
        });
        
        // Listen for typing completion
        $(document).on('typing:complete', function() {
            messageScroller.scrollToLatestMessage();
        });
        
        // Listen for job cards added
        $(document).on('jobCards:added', function() {
            setTimeout(() => {
                messageScroller.scrollToLatestMessage();
            }, 300);
        });
    }
    
    // Also watch for AJAX responses that might add messages
    if (window.XMLHttpRequest) {
        const originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function() {
            this.addEventListener('load', function() {
                // Check if response might contain messages
                if (this.responseText && (
                    this.responseText.includes('sffc-message') ||
                    this.responseText.includes('senna-message')
                )) {
                    setTimeout(() => {
                        messageScroller.scrollToLatestMessage();
                    }, 500);
                }
            });
            originalSend.apply(this, arguments);
        };
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);