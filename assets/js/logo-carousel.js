/**
 * Logo Carousel - Frontend JavaScript
 *
 * Handles smooth infinite scrolling for logo carousel
 */

(function($) {
    'use strict';

    const LogoCarousel = {
        init: function() {
            this.initCarousels();
        },

        initCarousels: function() {
            $('.sffc-logo-carousel').each(function() {
                const $carousel = $(this);
                const $track = $carousel.find('.sffc-logo-track');
                const speed = $carousel.data('speed') || 'medium';

                // Calculate proper animation duration based on content width
                LogoCarousel.setAnimationDuration($track, speed);

                // Recalculate on window resize
                $(window).on('resize', function() {
                    LogoCarousel.setAnimationDuration($track, speed);
                });
            });
        },

        setAnimationDuration: function($track, speed) {
            const trackWidth = $track[0].scrollWidth;
            const viewportWidth = $(window).width();

            // Base duration: pixels per second
            let pixelsPerSecond;
            switch(speed) {
                case 'slow':
                    pixelsPerSecond = 30;
                    break;
                case 'fast':
                    pixelsPerSecond = 80;
                    break;
                case 'medium':
                default:
                    pixelsPerSecond = 50;
                    break;
            }

            // Calculate duration for smooth scrolling
            const duration = (trackWidth / 2) / pixelsPerSecond;

            // Apply animation duration
            $track.css('animation-duration', duration + 's');
        },

        // Accessibility: Pause on focus
        handleAccessibility: function() {
            $('.sffc-logo-slide').on('focus', function() {
                $(this).closest('.sffc-logo-track').css('animation-play-state', 'paused');
            }).on('blur', function() {
                $(this).closest('.sffc-logo-track').css('animation-play-state', 'running');
            });
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        LogoCarousel.init();
        LogoCarousel.handleAccessibility();
    });

})(jQuery);
