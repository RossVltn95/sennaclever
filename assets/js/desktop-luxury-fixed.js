/**
 * Desktop Luxury Fixed - Clean Interactions
 * Simple, working JavaScript for premium desktop experience
 */

(function($) {
    'use strict';
    
    class DesktopLuxury {
        constructor() {
            this.shortlistOpen = false;
            this.init();
        }
        
        init() {
            // Only for desktop
            if (window.innerWidth < 1024) return;
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        setup() {
            this.fixLayout();
            this.createShortlistToggle();
            this.initShortlist();
            this.initSearch();
            this.initStageNavigation();
            this.smoothAnimations();
        }
        
        fixLayout() {
            // Remove any broken sidebars
            document.querySelectorAll('.luxury-sidebar, .sffc-sidebar').forEach(el => el.remove());
            
            // Ensure main container is centered
            const mainContainer = document.querySelector('.sffc-main-container');
            if (mainContainer) {
                mainContainer.style.paddingLeft = '0';
                mainContainer.style.marginLeft = '0';
            }
            
            // Hide floating menu bar
            const floatingMenu = document.querySelector('.sffc-floating-menu-bar');
            if (floatingMenu) {
                floatingMenu.style.display = 'none';
            }
            
            // Ensure conversational view is centered
            const convView = document.querySelector('.sffc-conversational-view');
            if (convView) {
                convView.style.margin = '0 auto';
                convView.style.maxWidth = '1000px';
            }
        }
        
        createShortlistToggle() {
            // Remove existing shortlist toggle
            document.querySelectorAll('.shortlist-toggle').forEach(el => el.remove());
        }
        
        initShortlist() {
            const shortlist = document.querySelector('.sffc-shortlist-floating');
            if (!shortlist) return;
            
            // Ensure it's properly positioned
            shortlist.style.position = 'fixed';
            shortlist.style.top = '72px';
            shortlist.style.right = '-320px';
            shortlist.style.bottom = '0';
            shortlist.style.width = '320px';
            shortlist.style.zIndex = '50';
            
            // Remove any tabs or broken elements
            document.querySelectorAll('.shortlist-tab').forEach(el => el.remove());
        }
        
        toggleShortlist() {
            // Handle the shortlist panel if it exists
            const shortlist = document.querySelector('.sffc-shortlist-floating');
            if (!shortlist) return;
            
            this.shortlistOpen = !this.shortlistOpen;
            
            if (this.shortlistOpen) {
                shortlist.classList.add('active');
                shortlist.style.right = '0';
                
                // Adjust content padding when open
                const convView = document.querySelector('.sffc-conversational-view');
                if (convView) {
                    convView.style.paddingRight = '368px';
                }
            } else {
                shortlist.classList.remove('active');
                shortlist.style.right = '-320px';
                
                // Reset content padding
                const convView = document.querySelector('.sffc-conversational-view');
                if (convView) {
                    convView.style.paddingRight = '48px';
                }
            }
        }
        
        initSearch() {
            const searchInput = document.querySelector('.sffc-search-input');
            if (!searchInput) return;
            
            // Simple search focus effect
            searchInput.addEventListener('focus', () => {
                searchInput.parentElement.classList.add('focused');
            });
            
            searchInput.addEventListener('blur', () => {
                searchInput.parentElement.classList.remove('focused');
            });
        }
        
        initStageNavigation() {
            const indicators = document.querySelectorAll('.stage-indicator');
            
            indicators.forEach(indicator => {
                indicator.addEventListener('click', (e) => {
                    // Remove active from all
                    indicators.forEach(ind => ind.classList.remove('active'));
                    
                    // Add active to clicked
                    e.currentTarget.classList.add('active');
                    
                    // Trigger stage change
                    const stage = e.currentTarget.dataset.stage;
                    this.switchStage(stage);
                });
            });
        }
        
        switchStage(stage) {
            if (window.sennaConversational) {
                switch(stage) {
                    case 'analyze':
                        window.sennaConversational.switchToAnalyze();
                        break;
                    case 'apply':
                        window.sennaConversational.switchToApply();
                        break;
                    default:
                        window.sennaConversational.switchToBrowse();
                }
            }
        }
        
        smoothAnimations() {
            // Animate messages on appearance
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationDelay = '0s';
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, { threshold: 0.1 });
            
            // Observe messages
            document.querySelectorAll('.senna-message').forEach(msg => {
                msg.style.animationPlayState = 'paused';
                observer.observe(msg);
            });
            
            // Observe new messages
            if (typeof jQuery !== 'undefined') {
                $(document).on('sennaMessageAdded', () => {
                    setTimeout(() => {
                        document.querySelectorAll('.senna-message:not([data-observed])').forEach(msg => {
                            msg.setAttribute('data-observed', 'true');
                            msg.style.animationPlayState = 'paused';
                            observer.observe(msg);
                        });
                    }, 100);
                });
            }
        }
    }
    
    // Initialize
    const desktopLuxury = new DesktopLuxury();
    
    // jQuery ready
    if (typeof jQuery !== 'undefined') {
        $(document).ready(() => {
            if (window.innerWidth >= 1024) {
                // Ensure initialization
                if (!window.desktopLuxuryInstance) {
                    window.desktopLuxuryInstance = new DesktopLuxury();
                }
            }
        });
        
        // Handle resize
        $(window).on('resize', () => {
            if (window.innerWidth >= 1024) {
                if (!window.desktopLuxuryInstance) {
                    window.desktopLuxuryInstance = new DesktopLuxury();
                }
            }
        });
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);