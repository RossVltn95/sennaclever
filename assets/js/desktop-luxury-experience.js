/**
 * Ultra Luxury Desktop Experience
 * Premium interactions and animations
 * 10x better than Perplexity
 */

(function($) {
    'use strict';
    
    class DesktopLuxuryExperience {
        constructor() {
            this.isScrolled = false;
            this.shortlistOpen = false;
            this.currentStage = 'browse';
            this.mouseX = 0;
            this.mouseY = 0;
            this.init();
        }
        
        init() {
            // Only initialize on desktop
            if (window.innerWidth < 1200) return;
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        }
        
        setup() {
            this.createLuxuryStructure();
            this.initScrollEffects();
            this.initHoverEffects();
            // Shortlist panel removed
            this.initSearchExperience();
            this.initMessageAnimations();
            this.initKeyboardShortcuts();
            this.initParallaxEffects();
            this.initSoundEffects();
            this.initPremiumTransitions();
        }
        
        createLuxuryStructure() {
            const wrapper = document.querySelector('.sffc-opportunities-wrapper');
            if (!wrapper) return;
            
            // Add luxury classes
            wrapper.classList.add('luxury-desktop');
            
            // Create sidebar if it doesn't exist
            if (!document.querySelector('.luxury-sidebar')) {
                this.createLuxurySidebar();
            }
            
            // Add header inner wrapper for grid layout
            const header = document.querySelector('.sffc-opp-header');
            if (header && !header.querySelector('.sffc-opp-header-inner')) {
                const headerContent = header.innerHTML;
                header.innerHTML = `<div class="sffc-opp-header-inner">${headerContent}</div>`;
            }
            
            // Shortlist tab removed
            
            // Add luxury touches
            this.addLuxuryDetails();
        }
        
        createLuxurySidebar() {
            const sidebar = document.createElement('aside');
            sidebar.className = 'luxury-sidebar';
            
            // Move floating menu into sidebar
            const floatingMenu = document.querySelector('.sffc-floating-menu-bar');
            if (floatingMenu) {
                sidebar.appendChild(floatingMenu);
                
                // Add section divider
                const divider = document.createElement('div');
                divider.className = 'sidebar-divider';
                sidebar.appendChild(divider);
                
                // Add premium sections
                this.addSidebarSections(sidebar);
            }
            
            // Insert after header
            const mainContainer = document.querySelector('.sffc-main-container');
            if (mainContainer) {
                mainContainer.parentNode.insertBefore(sidebar, mainContainer);
            }
        }
        
        addSidebarSections(sidebar) {
            // Recent Activity Section
            const recentSection = document.createElement('div');
            recentSection.className = 'sidebar-section';
            recentSection.innerHTML = `
                <h3 class="sidebar-section-title">Recent Activity</h3>
                <div class="sidebar-recent-items">
                    <div class="recent-item">
                        <span class="recent-icon">🔍</span>
                        <span class="recent-text">Searched "risk management"</span>
                    </div>
                    <div class="recent-item">
                        <span class="recent-icon">💼</span>
                        <span class="recent-text">Viewed 5 opportunities</span>
                    </div>
                    <div class="recent-item">
                        <span class="recent-icon">⭐</span>
                        <span class="recent-text">Saved 2 positions</span>
                    </div>
                </div>
            `;
            sidebar.appendChild(recentSection);
        }
        
        createShortlistTab() {
            // Removed - no longer using shortlist panel
        }
        
        addLuxuryDetails() {
            // Add gold accents
            this.addGoldAccents();
            
            // Add subtle animations
            this.addSubtleAnimations();
            
            // Add premium tooltips
            this.addPremiumTooltips();
        }
        
        addGoldAccents() {
            // Add gold line to active elements
            document.querySelectorAll('.stage-indicator.active, .sffc-menu-icon.active').forEach(el => {
                if (!el.querySelector('.gold-accent')) {
                    const accent = document.createElement('span');
                    accent.className = 'gold-accent';
                    el.appendChild(accent);
                }
            });
        }
        
        addSubtleAnimations() {
            // Animate elements on scroll
            const animateOnScroll = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('luxury-animated');
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.job-card-vogue, .senna-message').forEach(el => {
                animateOnScroll.observe(el);
            });
        }
        
        addPremiumTooltips() {
            // Custom tooltips for luxury feel
            document.querySelectorAll('[title]').forEach(el => {
                const title = el.getAttribute('title');
                el.removeAttribute('title');
                el.setAttribute('data-luxury-tooltip', title);
                
                el.addEventListener('mouseenter', (e) => this.showTooltip(e, title));
                el.addEventListener('mouseleave', () => this.hideTooltip());
            });
        }
        
        initScrollEffects() {
            const header = document.querySelector('.sffc-opp-header');
            const chatArea = document.querySelector('.sffc-chat-area');
            
            if (chatArea) {
                let lastScroll = 0;
                
                chatArea.addEventListener('scroll', () => {
                    const currentScroll = chatArea.scrollTop;
                    
                    // Header transformation
                    if (currentScroll > 50 && !this.isScrolled) {
                        header.classList.add('scrolled');
                        this.isScrolled = true;
                    } else if (currentScroll <= 50 && this.isScrolled) {
                        header.classList.remove('scrolled');
                        this.isScrolled = false;
                    }
                    
                    // Parallax for messages
                    this.applyParallax(currentScroll);
                    
                    lastScroll = currentScroll;
                });
            }
        }
        
        applyParallax(scrollY) {
            // Subtle parallax on job cards
            document.querySelectorAll('.job-card-vogue').forEach((card, index) => {
                const speed = 0.5 + (index * 0.1);
                const yPos = -(scrollY * speed / 10);
                card.style.transform = `translateY(${yPos}px)`;
            });
        }
        
        initHoverEffects() {
            // Track mouse for premium effects
            document.addEventListener('mousemove', (e) => {
                this.mouseX = e.clientX;
                this.mouseY = e.clientY;
                this.updateMouseEffects();
            });
            
            // Premium hover on cards
            document.querySelectorAll('.job-card-vogue').forEach(card => {
                card.addEventListener('mouseenter', (e) => this.cardHoverIn(e));
                card.addEventListener('mouseleave', (e) => this.cardHoverOut(e));
                card.addEventListener('mousemove', (e) => this.cardHoverMove(e));
            });
            
            // Button magnetic effect
            document.querySelectorAll('.senna-send-btn, .sffc-analyze-btn').forEach(btn => {
                btn.addEventListener('mousemove', (e) => this.magneticEffect(e, btn));
                btn.addEventListener('mouseleave', (e) => this.resetMagnetic(btn));
            });
        }
        
        cardHoverIn(e) {
            const card = e.currentTarget;
            card.style.transition = 'none';
            
            // Create glow effect
            const glow = document.createElement('div');
            glow.className = 'card-glow';
            card.appendChild(glow);
            
            // Animate in
            requestAnimationFrame(() => {
                glow.style.opacity = '1';
            });
        }
        
        cardHoverOut(e) {
            const card = e.currentTarget;
            card.style.transition = '';
            card.style.transform = '';
            
            // Remove glow
            const glow = card.querySelector('.card-glow');
            if (glow) {
                glow.style.opacity = '0';
                setTimeout(() => glow.remove(), 300);
            }
        }
        
        cardHoverMove(e) {
            const card = e.currentTarget;
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            card.style.transform = `
                perspective(1000px)
                rotateX(${rotateX}deg)
                rotateY(${rotateY}deg)
                translateZ(10px)
            `;
            
            // Move glow with mouse
            const glow = card.querySelector('.card-glow');
            if (glow) {
                glow.style.background = `
                    radial-gradient(
                        circle at ${x}px ${y}px,
                        rgba(212, 175, 55, 0.3),
                        transparent
                    )
                `;
            }
        }
        
        magneticEffect(e, element) {
            const rect = element.getBoundingClientRect();
            const x = e.clientX - (rect.left + rect.width / 2);
            const y = e.clientY - (rect.top + rect.height / 2);
            
            element.style.transform = `translate(${x * 0.2}px, ${y * 0.2}px) scale(1.05)`;
        }
        
        resetMagnetic(element) {
            element.style.transform = '';
        }
        
        updateMouseEffects() {
            // Update CSS variables for mouse position
            document.documentElement.style.setProperty('--mouse-x', `${this.mouseX}px`);
            document.documentElement.style.setProperty('--mouse-y', `${this.mouseY}px`);
        }
        
        initShortlistPanel() {
            // Removed - no longer using shortlist panel
        }
        
        toggleShortlist() {
            // Removed - no longer using shortlist panel
        }
        
        updateShortlistCount() {
            // Removed - no longer using shortlist panel
        }
        
        initSearchExperience() {
            const searchInput = document.querySelector('.sffc-search-input');
            if (!searchInput) return;
            
            // Premium search effects
            searchInput.addEventListener('focus', () => {
                this.activateSearchMode();
            });
            
            searchInput.addEventListener('blur', () => {
                this.deactivateSearchMode();
            });
            
            // Live search preview
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performLuxurySearch(e.target.value);
                }, 300);
            });
        }
        
        activateSearchMode() {
            document.body.classList.add('search-active');
            
            // Dim other elements
            const overlay = document.createElement('div');
            overlay.className = 'search-overlay';
            document.body.appendChild(overlay);
            
            // Animate search bar
            const searchWrapper = document.querySelector('.sffc-search-wrapper');
            if (searchWrapper) {
                searchWrapper.classList.add('search-focused');
            }
        }
        
        deactivateSearchMode() {
            document.body.classList.remove('search-active');
            
            const overlay = document.querySelector('.search-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(() => overlay.remove(), 300);
            }
            
            const searchWrapper = document.querySelector('.sffc-search-wrapper');
            if (searchWrapper) {
                searchWrapper.classList.remove('search-focused');
            }
        }
        
        performLuxurySearch(query) {
            // Trigger message search with animation
            if (window.messageSearch) {
                window.messageSearch.simpleTextSearch(query);
            }
            
            // Show search results with luxury animation
            this.animateSearchResults();
        }
        
        animateSearchResults() {
            const highlights = document.querySelectorAll('.message-search-highlight');
            highlights.forEach((highlight, index) => {
                highlight.style.animation = `luxuryHighlight 0.5s ${index * 0.1}s ease`;
            });
        }
        
        initMessageAnimations() {
            // Observe new messages
            const messageObserver = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.classList && node.classList.contains('senna-message')) {
                            this.animateNewMessage(node);
                        }
                    });
                });
            });
            
            const chatArea = document.querySelector('.sffc-chat-area');
            if (chatArea) {
                messageObserver.observe(chatArea, { childList: true, subtree: true });
            }
        }
        
        animateNewMessage(message) {
            // Add luxury entrance animation
            message.style.opacity = '0';
            message.style.transform = 'translateY(20px) scale(0.95)';
            
            requestAnimationFrame(() => {
                message.style.transition = 'all 0.6s cubic-bezier(0.23, 1, 0.32, 1)';
                message.style.opacity = '1';
                message.style.transform = 'translateY(0) scale(1)';
            });
            
            // Play sound
            this.playSound('message');
        }
        
        initKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Cmd/Ctrl + K for search
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    document.querySelector('.sffc-search-input')?.focus();
                }
                
                // Cmd/Ctrl + S for shortlist
                if ((e.metaKey || e.ctrlKey) && e.key === 's') {
                    e.preventDefault();
                    this.toggleShortlist();
                }
                
                // Cmd/Ctrl + Enter to send message
                if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                    e.preventDefault();
                    document.querySelector('.senna-send-btn')?.click();
                }
                
                // ESC to close modals
                if (e.key === 'Escape') {
                    this.closeAllModals();
                }
            });
        }
        
        initParallaxEffects() {
            // Background parallax
            document.addEventListener('mousemove', (e) => {
                const x = (e.clientX / window.innerWidth - 0.5) * 20;
                const y = (e.clientY / window.innerHeight - 0.5) * 20;
                
                const wrapper = document.querySelector('.sffc-opportunities-wrapper');
                if (wrapper) {
                    wrapper.style.backgroundPosition = `${50 + x}% ${50 + y}%`;
                }
            });
        }
        
        initSoundEffects() {
            // Create audio context for premium sounds
            this.audioContext = null;
            this.sounds = {
                message: { frequency: 800, duration: 100 },
                open: { frequency: 600, duration: 150 },
                close: { frequency: 400, duration: 150 },
                hover: { frequency: 1000, duration: 50 }
            };
        }
        
        playSound(type) {
            // Create subtle UI sounds
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            const sound = this.sounds[type];
            if (!sound) return;
            
            const oscillator = this.audioContext.createOscillator();
            const gainNode = this.audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(this.audioContext.destination);
            
            oscillator.frequency.value = sound.frequency;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, this.audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + sound.duration / 1000);
            
            oscillator.start(this.audioContext.currentTime);
            oscillator.stop(this.audioContext.currentTime + sound.duration / 1000);
        }
        
        initPremiumTransitions() {
            // Page transitions
            document.querySelectorAll('.stage-indicator').forEach(indicator => {
                indicator.addEventListener('click', (e) => {
                    this.transitionStage(e.currentTarget.dataset.stage);
                });
            });
        }
        
        transitionStage(newStage) {
            if (newStage === this.currentStage) return;
            
            const chatArea = document.querySelector('.sffc-chat-area');
            if (!chatArea) return;
            
            // Fade out
            chatArea.style.opacity = '0';
            chatArea.style.transform = 'scale(0.98)';
            
            setTimeout(() => {
                // Update stage
                this.currentStage = newStage;
                
                // Update UI
                document.querySelectorAll('.stage-indicator').forEach(ind => {
                    ind.classList.remove('active');
                });
                document.querySelector(`[data-stage="${newStage}"]`)?.classList.add('active');
                
                // Trigger stage change in MENA Careers
                if (window.sennaConversational) {
                    switch(newStage) {
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
                
                // Fade in
                chatArea.style.opacity = '1';
                chatArea.style.transform = 'scale(1)';
            }, 300);
        }
        
        showTooltip(e, text) {
            const tooltip = document.createElement('div');
            tooltip.className = 'luxury-tooltip';
            tooltip.textContent = text;
            tooltip.style.left = `${e.clientX}px`;
            tooltip.style.top = `${e.clientY - 40}px`;
            
            document.body.appendChild(tooltip);
            
            requestAnimationFrame(() => {
                tooltip.classList.add('visible');
            });
        }
        
        hideTooltip() {
            const tooltip = document.querySelector('.luxury-tooltip');
            if (tooltip) {
                tooltip.classList.remove('visible');
                setTimeout(() => tooltip.remove(), 300);
            }
        }
        
        closeAllModals() {
            // Close shortlist
            if (this.shortlistOpen) {
                this.toggleShortlist();
            }
            
            // Close profile builder
            const profileOverlay = document.querySelector('.sffc-profile-builder-overlay');
            if (profileOverlay) {
                profileOverlay.classList.remove('active');
            }
            
            // Deactivate search
            this.deactivateSearchMode();
        }
    }
    
    // Initialize
    const luxuryDesktop = new DesktopLuxuryExperience();
    
    // Make globally available
    window.DesktopLuxuryExperience = DesktopLuxuryExperience;
    
    // jQuery integration
    if (typeof jQuery !== 'undefined') {
        $(document).ready(() => {
            if (window.innerWidth >= 1200 && !window.luxuryDesktopInstance) {
                window.luxuryDesktopInstance = new DesktopLuxuryExperience();
            }
        });
        
        // Handle resize
        $(window).on('resize', () => {
            if (window.innerWidth >= 1200 && !window.luxuryDesktopInstance) {
                window.luxuryDesktopInstance = new DesktopLuxuryExperience();
            } else if (window.innerWidth < 1200 && window.luxuryDesktopInstance) {
                // Clean up for mobile
                document.querySelector('.luxury-sidebar')?.remove();
                document.querySelector('.shortlist-tab')?.remove();
                window.luxuryDesktopInstance = null;
            }
        });
    }
    
})(typeof jQuery !== 'undefined' ? jQuery : null);