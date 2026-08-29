(function($) {
    'use strict';

    // Professional Profile Magazine Experience
    const SennaMagazineProfile = {
        init: function() {
            this.initCarouselNavigation();
            this.initSectionAnimations();
            this.initSmoothScrolling();
            this.initIntersectionObserver();
            this.bindEvents();
        },

        initCarouselNavigation: function() {
            const navButtons = $('.senna-nav-button');
            const sections = $('.senna-magazine-section');
            
            navButtons.on('click', function() {
                const targetSection = $(this).data('section');
                
                // Update active button
                navButtons.removeClass('active');
                $(this).addClass('active');
                
                // Show target section
                sections.removeClass('visible');
                $(`#${targetSection}`).addClass('visible');
                
                // Smooth scroll to section
                $('html, body').animate({
                    scrollTop: $(`#${targetSection}`).offset().top - 200
                }, 600, 'easeInOutQuart');
            });
        },

        initSectionAnimations: function() {
            // Stagger animation for cards
            $('.stagger-children').each(function() {
                const children = $(this).children();
                children.each(function(index) {
                    $(this).css('animation-delay', `${(index + 1) * 0.1}s`);
                });
            });

            // Fade in sections on scroll
            const observerOptions = {
                root: null,
                rootMargin: '-50px',
                threshold: 0.1
            };

            const sectionObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('fade-in-up');
                    }
                });
            }, observerOptions);

            $('.senna-magazine-section').each(function() {
                sectionObserver.observe(this);
            });
        },

        initSmoothScrolling: function() {
            // Add smooth scrolling to all internal links
            $('a[href^="#"]').on('click', function(e) {
                e.preventDefault();
                const target = $(this.getAttribute('href'));
                if (target.length) {
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 800, 'easeInOutQuart');
                }
            });
        },

        initIntersectionObserver: function() {
            // Navigation highlighting based on visible sections
            const navObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const sectionId = $(entry.target).attr('id');
                        $('.senna-nav-button').removeClass('active');
                        $(`.senna-nav-button[data-section="${sectionId}"]`).addClass('active');
                    }
                });
            }, {
                root: null,
                rootMargin: '-200px 0px -200px 0px',
                threshold: 0.3
            });

            $('.senna-magazine-section').each(function() {
                navObserver.observe(this);
            });

            // Card hover effects with enhanced animation
            $('.senna-trending-card, .senna-intro-card, .senna-takeaway-card').each(function() {
                const card = $(this);
                
                card.on('mouseenter', function() {
                    $(this).addClass('card-hover-active');
                    
                    // Add subtle parallax effect
                    $(this).find('.senna-card-title, .senna-takeaway-title').css({
                        'transform': 'translateY(-2px)',
                        'transition': 'transform 0.3s ease'
                    });
                });
                
                card.on('mouseleave', function() {
                    $(this).removeClass('card-hover-active');
                    
                    $(this).find('.senna-card-title, .senna-takeaway-title').css({
                        'transform': 'translateY(0)',
                        'transition': 'transform 0.3s ease'
                    });
                });
            });
        },

        bindEvents: function() {
            // Enhanced underline animation
            $('.underline-animation').on('mouseenter', function() {
                $(this).css('background-size', '100% 1px');
            }).on('mouseleave', function() {
                $(this).css('background-size', '0 1px');
            });

            // Dynamic carousel scrolling
            const carousel = $('.senna-nav-carousel');
            let isScrolling = false;

            carousel.on('wheel', function(e) {
                if (!isScrolling) {
                    isScrolling = true;
                    e.preventDefault();
                    
                    const scrollLeft = this.scrollLeft + (e.originalEvent.deltaY > 0 ? 100 : -100);
                    $(this).animate({ scrollLeft: scrollLeft }, 300, function() {
                        isScrolling = false;
                    });
                }
            });

            // Professional card interactions
            $('.senna-expertise-tag').on('click', function() {
                const expertise = $(this).text();
                SennaMagazineProfile.filterContentByExpertise(expertise);
            });

            // Enhanced intro card interactions
            $('.senna-intro-card').on('click', function() {
                const name = $(this).find('.senna-intro-name').text();
                SennaMagazineProfile.showIntroductionModal(name);
            });

            // Reading progress indicator
            $(window).on('scroll', this.updateReadingProgress);
            
            // Lazy loading enhancement
            this.initLazyLoading();
        },

        filterContentByExpertise: function(expertise) {
            // Track expertise interaction
            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_track_profile_interaction',
                interaction_action: 'expertise_filter',
                details: { expertise: expertise },
                nonce: sffc_ajax.nonce
            });
            
            // Filter trending topics based on expertise
            $('.senna-trending-card').each(function() {
                const cardCategory = $(this).find('.senna-card-category').text();
                const cardTitle = $(this).find('.senna-card-title').text();
                
                if (cardCategory.includes(expertise) || cardTitle.toLowerCase().includes(expertise.toLowerCase())) {
                    $(this).removeClass('filtered-out').addClass('highlighted');
                } else {
                    $(this).addClass('filtered-out').removeClass('highlighted');
                }
            });

            // Auto-scroll to trending section
            $('html, body').animate({
                scrollTop: $('#trending').offset().top - 150
            }, 600);

            // Remove filter after 3 seconds
            setTimeout(() => {
                $('.senna-trending-card').removeClass('filtered-out highlighted');
            }, 3000);
        },

        showIntroductionModal: function(name) {
            // Create a sophisticated modal for introduction details
            const modal = $(`
                <div class="senna-intro-modal" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(13, 53, 62, 0.95);
                    backdrop-filter: blur(20px);
                    z-index: 1000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div class="senna-intro-modal-content" style="
                        background: white;
                        border-radius: 1rem;
                        padding: 3rem;
                        max-width: 500px;
                        width: 90%;
                        text-align: center;
                        transform: translateY(2rem);
                        opacity: 0;
                        transition: all 0.3s ease;
                    ">
                        <h3 style="font-family: var(--font-serif); margin-bottom: 1rem; color: var(--senna-ink);">
                            Express Interest
                        </h3>
                        <p style="color: var(--senna-neutral-600); margin-bottom: 2rem;">
                            Connect with ${name} through your professional network
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button class="senna-modal-btn" style="
                                background: var(--senna-ink);
                                color: white;
                                border: none;
                                padding: 0.75rem 1.5rem;
                                border-radius: 0.5rem;
                                cursor: pointer;
                            ">Express Interest</button>
                            <button class="senna-modal-close" style="
                                background: transparent;
                                color: var(--senna-neutral-600);
                                border: 1px solid var(--senna-neutral-300);
                                padding: 0.75rem 1.5rem;
                                border-radius: 0.5rem;
                                cursor: pointer;
                            ">Close</button>
                        </div>
                    </div>
                </div>
            `);

            $('body').append(modal);

            // Animate modal in
            setTimeout(() => {
                modal.find('.senna-intro-modal-content').css({
                    'transform': 'translateY(0)',
                    'opacity': '1'
                });
            }, 50);

            // Close modal events
            modal.on('click', function(e) {
                if (e.target === this) {
                    SennaMagazineProfile.closeModal(modal);
                }
            });

            modal.find('.senna-modal-close').on('click', function() {
                SennaMagazineProfile.closeModal(modal);
            });

            modal.find('.senna-modal-btn').on('click', function() {
                const button = $(this);
                button.text('Sending...').prop('disabled', true);
                
                // Send introduction request via AJAX
                $.post(sffc_ajax.ajax_url, {
                    action: 'sffc_request_introduction',
                    target_name: name,
                    introduction_context: 'Professional networking through MENA Careers platform',
                    introduction_reason: 'Mutual professional interests',
                    mutual_interest: 'Private equity and investment expertise',
                    nonce: sffc_ajax.nonce
                }, function(response) {
                    if (response.success) {
                        button.text('Request Sent');
                        setTimeout(() => {
                            SennaMagazineProfile.closeModal(modal);
                        }, 1500);
                    } else {
                        button.text('Failed - Try Again').prop('disabled', false);
                    }
                });
            });
        },

        closeModal: function(modal) {
            modal.find('.senna-intro-modal-content').css({
                'transform': 'translateY(2rem)',
                'opacity': '0'
            });
            
            setTimeout(() => {
                modal.remove();
            }, 300);
        },

        updateReadingProgress: function() {
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();
            const scrollTop = $(window).scrollTop();
            const progress = (scrollTop / (documentHeight - windowHeight)) * 100;

            // Create or update progress bar
            let progressBar = $('.senna-reading-progress');
            if (progressBar.length === 0) {
                progressBar = $(`
                    <div class="senna-reading-progress" style="
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 0%;
                        height: 3px;
                        background: var(--senna-mint);
                        z-index: 100;
                        transition: width 0.1s ease;
                    "></div>
                `);
                $('body').append(progressBar);
            }

            progressBar.css('width', `${Math.min(progress, 100)}%`);
        },

        initLazyLoading: function() {
            // Enhanced lazy loading for better performance
            const images = $('img[data-src]');
            
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = $(entry.target);
                            img.attr('src', img.data('src'));
                            img.removeClass('lazy');
                            imageObserver.unobserve(entry.target);
                        }
                    });
                });

                images.each(function() {
                    imageObserver.observe(this);
                });
            }
        }
    };

    // Make SennaMagazineProfile globally available
    window.SennaMagazineProfile = SennaMagazineProfile;

    // Add custom easing function for smooth animations
    $.easing.easeInOutQuart = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t * t + b;
        return -c / 2 * ((t -= 2) * t * t * t - 2) + b;
    };

    // Performance optimization: Debounce scroll events
    function debounce(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // Apply debouncing to scroll-intensive functions
    $(window).on('scroll', debounce(SennaMagazineProfile.updateReadingProgress, 10));

// CSS for dynamic enhancements
const magazineStyles = `
    <style>
    .filtered-out {
        opacity: 0.3;
        transform: scale(0.95);
        transition: all 0.3s ease;
    }
    
    .highlighted {
        transform: scale(1.02);
        box-shadow: 0 10px 30px rgba(151, 255, 213, 0.3);
        border: 1px solid var(--senna-mint);
        transition: all 0.3s ease;
    }
    
    .card-hover-active {
        transform: translateY(-4px) scale(1.01);
        transition: transform 0.3s ease;
    }
    
    .lazy {
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .senna-modal-btn:hover {
        background: #1a4a57 !important;
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    
    .senna-modal-close:hover {
        background: var(--senna-neutral-100) !important;
        transition: all 0.2s ease;
    }
    </style>
`;

// Inject dynamic styles
$('head').append(magazineStyles);

// Initialize on DOM ready
$(document).ready(function() {
    if (typeof SennaMagazineProfile !== 'undefined') {
        SennaMagazineProfile.init();
    }
});

})(jQuery);
