/**
 * Interested Counter
 * Shows counter when btn-interested is clicked
 */

(function($) {
    'use strict';
    
    class InterestedCounter {
        constructor() {
            this.count = 0;
            this.init();
        }
        
        init() {
            // Load existing count from localStorage
            this.count = parseInt(localStorage.getItem('interestedCount') || '0');
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        setup() {
            // Add counter badge to trigger button
            this.addCounterBadge();
            
            // Update counter display
            this.updateCounter();
            
            // Bind click events to interested buttons
            this.bindInterestedButtons();
            
            // Watch for new interested buttons
            this.observeNewButtons();
        }
        
        /**
         * Add counter badge to header area
         */
        addCounterBadge() {
            // Counter badge functionality disabled - no longer needed
            return;
        }
        
        /**
         * Update counter display
         */
        updateCounter() {
            const badge = document.querySelector('.interested-counter-badge');
            if (!badge) {
                this.addCounterBadge();
                return;
            }
            
            if (this.count > 0) {
                badge.textContent = this.count;
                badge.style.display = 'flex';
                
                // Add pulse animation when updated
                badge.style.animation = 'pulse 0.3s ease';
                setTimeout(() => {
                    badge.style.animation = '';
                }, 300);
            } else {
                badge.style.display = 'none';
            }
            
            // Save to localStorage
            localStorage.setItem('interestedCount', this.count.toString());
        }
        
        /**
         * Bind click events to interested buttons
         */
        bindInterestedButtons() {
            // Select all interested buttons
            const buttons = document.querySelectorAll('.vogue-action.btn-interested.primary, .btn-interested');
            
            buttons.forEach(button => {
                // Check if already bound
                if (button.dataset.counterBound === 'true') return;
                
                button.dataset.counterBound = 'true';
                
                button.addEventListener('click', (e) => {
                    // Check if already interested (toggle behavior)
                    const isInterested = button.classList.contains('interested') || 
                                       button.textContent.toLowerCase().includes('saved');
                    
                    if (!isInterested) {
                        // Increment counter
                        this.count++;
                        console.log('Interested count increased:', this.count);
                        
                        // Mark as interested
                        button.classList.add('interested');
                        
                        // Update button text if needed
                        if (button.textContent.toLowerCase().includes('interested')) {
                            button.innerHTML = button.innerHTML.replace(/interested/i, 'Saved');
                        }
                    } else {
                        // Decrement counter
                        if (this.count > 0) {
                            this.count--;
                            console.log('Interested count decreased:', this.count);
                        }
                        
                        // Remove interested state
                        button.classList.remove('interested');
                        
                        // Update button text if needed
                        if (button.textContent.toLowerCase().includes('saved')) {
                            button.innerHTML = button.innerHTML.replace(/saved/i, 'Interested');
                        }
                    }
                    
                    // Update counter display
                    this.updateCounter();
                    
                    // Trigger event for other components
                    $(document).trigger('interestedCountChanged', [this.count]);
                });
            });
        }
        
        /**
         * Observe for new interested buttons
         */
        observeNewButtons() {
            const observer = new MutationObserver((mutations) => {
                let shouldBind = false;
                
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) {
                            // Check if node is or contains interested button
                            if (node.classList && (
                                node.classList.contains('btn-interested') ||
                                (node.classList.contains('vogue-action') && node.classList.contains('primary'))
                            )) {
                                shouldBind = true;
                            } else if (node.querySelector) {
                                const buttons = node.querySelectorAll('.vogue-action.btn-interested.primary, .btn-interested');
                                if (buttons.length > 0) {
                                    shouldBind = true;
                                }
                            }
                        }
                    });
                });
                
                if (shouldBind) {
                    this.bindInterestedButtons();
                }
            });
            
            // Observe the entire document for new buttons
            observer.observe(document.body, { 
                childList: true, 
                subtree: true 
            });
        }
        
        /**
         * Reset counter
         */
        resetCounter() {
            this.count = 0;
            this.updateCounter();
            
            // Remove interested state from all buttons
            document.querySelectorAll('.btn-interested.interested').forEach(button => {
                button.classList.remove('interested');
                if (button.textContent.toLowerCase().includes('saved')) {
                    button.innerHTML = button.innerHTML.replace(/saved/i, 'Interested');
                }
            });
        }
        
        /**
         * Get current count
         */
        getCount() {
            return this.count;
        }
    }
    
    // Initialize
    const interestedCounter = new InterestedCounter();
    
    // Make available globally
    window.interestedCounter = interestedCounter;
    
    // jQuery integration
    if (typeof jQuery !== 'undefined') {
        $(document).ready(() => {
            // Reinitialize after a delay to catch dynamic content
            setTimeout(() => {
                interestedCounter.bindInterestedButtons();
            }, 500);
        });
        
        // Listen for saved jobs updates
        $(document).on('savedJobsUpdated', () => {
            const savedJobs = JSON.parse(localStorage.getItem('savedJobs') || '[]');
            interestedCounter.count = savedJobs.length;
            interestedCounter.updateCounter();
        });
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);

// Add CSS for pulse animation
const style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.2);
        }
        100% {
            transform: scale(1);
        }
    }
    
    .btn-interested.interested {
        background: #4CAF50 !important;
        color: white !important;
        border-color: #4CAF50 !important;
    }
    
`;
document.head.appendChild(style);