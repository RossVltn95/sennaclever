/**
 * Premium Article Renderer - Interactive Features and Animations
 * Sophisticated typography animations and reading enhancements
 */

(function($) {
    'use strict';
    
    // Main Premium Article Class
    window.SFFCPremiumArticle = {
        
        // Configuration
        config: {
            animationThreshold: 0.1,
            readingProgressThreshold: 0.05,
            typingDelay: 50,
            fadeInDuration: 600,
            staggerDelay: 100,
        },
        
        // Initialize all functionality
        init: function() {
            this.initIntersectionObserver();
            this.initShareButtons();
            this.initBookmarkButtons();
            this.initSmoothScrolling();
            this.initKeyboardNavigation();
            this.initSearchFunctionality();
        },
        
        // Initialize Intersection Observer for animations
        initIntersectionObserver: function() {
            if (!('IntersectionObserver' in window)) {
                // Fallback for older browsers
                $('.sffc-animate-in').addClass('in-view');
                return;
            }
            
            const observerOptions = {
                threshold: this.config.animationThreshold,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('in-view');
                        // Unobserve after animation to improve performance
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);
            
            // Observe all animatable elements
            $('.sffc-animate-in').each(function() {
                observer.observe(this);
            });
        },
        
        // Initialize typography animations for specific article
        initTypographyAnimations: function(articleId) {
            const $article = $('#' + articleId);
            if (!$article.length) return;
            
            // Enhance drop caps with animation
            this.animateDropCaps($article);
            
            // Add link hover effects
            this.enhanceLinks($article);
            
            // Add paragraph highlighting on scroll
            this.initParagraphHighlighting($article);
            
            // Initialize quote animations
            this.animateQuotes($article);
        },
        
        // Animate drop caps
        animateDropCaps: function($article) {
            $article.find('.sffc-enhanced-paragraph:first-letter').each(function() {
                const $dropCap = $(this);
                
                // Create animated drop cap wrapper
                const dropCapText = $dropCap.text().charAt(0);
                $dropCap.html('<span class="sffc-drop-cap-animated">' + dropCapText + '</span>' + $dropCap.text().substring(1));
                
                // Add animation on scroll into view
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            $(entry.target).find('.sffc-drop-cap-animated').addClass('animate-in');
                        }
                    });
                }, { threshold: 0.5 });
                
                observer.observe(this);
            });
        },
        
        // Enhance links with sophisticated hover effects
        enhanceLinks: function($article) {
            $article.find('a').each(function() {
                const $link = $(this);
                
                // Add sophisticated underline animation
                if (!$link.hasClass('sffc-enhanced-link')) {
                    $link.addClass('sffc-enhanced-link');
                    $link.wrap('<span class="sffc-link-wrapper"></span>');
                }
            });
        },
        
        // Initialize paragraph highlighting on scroll
        initParagraphHighlighting: function($article) {
            const $paragraphs = $article.find('.sffc-enhanced-paragraph');
            
            if (!$paragraphs.length) return;
            
            const observerOptions = {
                threshold: 0.7,
                rootMargin: '-20% 0px -20% 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const $paragraph = $(entry.target);
                    
                    if (entry.isIntersecting) {
                        $paragraph.addClass('sffc-paragraph-active');
                        // Remove active class from siblings
                        $paragraph.siblings('.sffc-enhanced-paragraph').removeClass('sffc-paragraph-active');
                    }
                });
            }, observerOptions);
            
            $paragraphs.each(function() {
                observer.observe(this);
            });
        },
        
        // Animate blockquotes
        animateQuotes: function($article) {
            $article.find('.sffc-enhanced-blockquote').each(function() {
                const $quote = $(this);
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            $quote.addClass('sffc-quote-animated');
                            
                            // Typewriter effect for quote content (optional)
                            if ($quote.data('typewriter') === 'true') {
                                this.typewriterEffect($quote);
                            }
                        }
                    });
                }, { threshold: 0.3 });
                
                observer.observe(this);
            });
        },
        
        // Initialize reading progress for specific article
        initReadingProgress: function(articleId, progressId) {
            const $article = $('#' + articleId);
            const $progressBar = $('#' + progressId);
            const $progressContainer = $progressBar.parent();
            
            if (!$article.length || !$progressBar.length) return;
            
            // Calculate reading progress
            const updateProgress = () => {
                const articleTop = $article.offset().top;
                const articleHeight = $article.outerHeight();
                const windowHeight = $(window).height();
                const scrollTop = $(window).scrollTop();
                
                // Show progress bar when article is in view
                if (scrollTop > articleTop - windowHeight && scrollTop < articleTop + articleHeight) {
                    $progressContainer.addClass('active');
                    
                    // Calculate progress percentage
                    const progress = Math.min(100, Math.max(0, 
                        ((scrollTop - articleTop + windowHeight) / (articleHeight + windowHeight)) * 100
                    ));
                    
                    $progressBar.css('width', progress + '%');
                } else {
                    $progressContainer.removeClass('active');
                }
            };
            
            // Throttled scroll handler
            let ticking = false;
            $(window).on('scroll.premium-article', function() {
                if (!ticking) {
                    requestAnimationFrame(() => {
                        updateProgress();
                        ticking = false;
                    });
                    ticking = true;
                }
            });
            
            // Initial calculation
            updateProgress();
        },
        
        // Initialize share buttons
        initShareButtons: function() {
            $(document).on('click', '.sffc-share-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const url = encodeURIComponent(window.location.href);
                const title = encodeURIComponent(document.title);
                
                // Check if native sharing is available
                if (navigator.share) {
                    navigator.share({
                        title: document.title,
                        url: window.location.href
                    }).catch(console.error);
                } else {
                    // Fallback: create share menu
                    this.showShareMenu($btn, url, title);
                }
            });
        },
        
        // Show share menu
        showShareMenu: function($btn, url, title) {
            const shareMenu = `
                <div class="sffc-share-menu">
                    <a href="https://twitter.com/intent/tweet?url=${url}&text=${title}" target="_blank" class="sffc-share-option sffc-share-twitter">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        Twitter
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=${url}" target="_blank" class="sffc-share-option sffc-share-linkedin">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        LinkedIn
                    </a>
                    <a href="mailto:?subject=${title}&body=${url}" class="sffc-share-option sffc-share-email">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636C.732 21.003 0 20.271 0 19.366V5.457c0-.887.711-1.636 1.636-1.636h.727L12 10.18l9.637-6.359h.727c.905 0 1.636.749 1.636 1.636z"/></svg>
                        Email
                    </a>
                </div>
            `;
            
            // Remove existing menu
            $('.sffc-share-menu').remove();
            
            // Add menu to page
            $('body').append(shareMenu);
            
            // Position menu
            const $menu = $('.sffc-share-menu');
            const btnOffset = $btn.offset();
            
            $menu.css({
                top: btnOffset.top + $btn.outerHeight() + 10,
                left: btnOffset.left - $menu.outerWidth() / 2 + $btn.outerWidth() / 2
            }).addClass('active');
            
            // Close menu when clicking outside
            $(document).one('click', function() {
                $menu.removeClass('active');
                setTimeout(() => $menu.remove(), 300);
            });
        },
        
        // Initialize bookmark buttons
        initBookmarkButtons: function() {
            $(document).on('click', '.sffc-bookmark-btn', function(e) {
                e.preventDefault();
                
                const $btn = $(this);
                const postId = $btn.closest('.sffc-premium-article').attr('id').replace('sffc-premium-article-', '');
                
                // Toggle bookmark state
                $btn.toggleClass('bookmarked');
                
                // Save to localStorage (you can replace this with AJAX call to server)
                const bookmarks = JSON.parse(localStorage.getItem('sffc_bookmarks') || '[]');
                
                if ($btn.hasClass('bookmarked')) {
                    if (!bookmarks.includes(postId)) {
                        bookmarks.push(postId);
                        this.showBookmarkNotification('Article bookmarked!', 'success');
                    }
                } else {
                    const index = bookmarks.indexOf(postId);
                    if (index > -1) {
                        bookmarks.splice(index, 1);
                        this.showBookmarkNotification('Bookmark removed', 'info');
                    }
                }
                
                localStorage.setItem('sffc_bookmarks', JSON.stringify(bookmarks));
            });
            
            // Load existing bookmarks
            this.loadBookmarkStates();
        },
        
        // Load bookmark states from storage
        loadBookmarkStates: function() {
            const bookmarks = JSON.parse(localStorage.getItem('sffc_bookmarks') || '[]');
            
            $('.sffc-bookmark-btn').each(function() {
                const $btn = $(this);
                const postId = $btn.closest('.sffc-premium-article').attr('id').replace('sffc-premium-article-', '');
                
                if (bookmarks.includes(postId)) {
                    $btn.addClass('bookmarked');
                }
            });
        },
        
        // Show bookmark notification
        showBookmarkNotification: function(message, type) {
            const notification = `
                <div class="sffc-notification sffc-notification-${type}">
                    <span class="sffc-notification-message">${message}</span>
                    <button class="sffc-notification-close">×</button>
                </div>
            `;
            
            $('body').append(notification);
            
            const $notification = $('.sffc-notification').last();
            
            // Show notification
            setTimeout(() => $notification.addClass('active'), 100);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                $notification.removeClass('active');
                setTimeout(() => $notification.remove(), 300);
            }, 3000);
            
            // Close button
            $notification.find('.sffc-notification-close').on('click', function() {
                $notification.removeClass('active');
                setTimeout(() => $notification.remove(), 300);
            });
        },
        
        // Initialize smooth scrolling
        initSmoothScrolling: function() {
            // Smooth scroll to anchor links
            $(document).on('click', 'a[href^="#"]', function(e) {
                const target = $(this.getAttribute('href'));
                
                if (target.length) {
                    e.preventDefault();
                    
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 600, 'easeInOutQuart');
                }
            });
        },
        
        // Initialize keyboard navigation
        initKeyboardNavigation: function() {
            $(document).on('keydown', function(e) {
                // Press 'S' to share
                if (e.key === 's' || e.key === 'S') {
                    if (!e.ctrlKey && !e.metaKey && !$(e.target).is('input, textarea')) {
                        e.preventDefault();
                        $('.sffc-share-btn').first().click();
                    }
                }
                
                // Press 'B' to bookmark
                if (e.key === 'b' || e.key === 'B') {
                    if (!e.ctrlKey && !e.metaKey && !$(e.target).is('input, textarea')) {
                        e.preventDefault();
                        $('.sffc-bookmark-btn').first().click();
                    }
                }
            });
        },
        
        // Typewriter effect for special content
        typewriterEffect: function($element) {
            const text = $element.text();
            const $wrapper = $('<span class="sffc-typewriter-wrapper"></span>');
            
            $element.html($wrapper);
            
            let index = 0;
            const typeChar = () => {
                if (index < text.length) {
                    $wrapper.append(text[index]);
                    index++;
                    setTimeout(typeChar, this.config.typingDelay);
                }
            };
            
            typeChar();
        },

        // Initialize search functionality
        initSearchFunctionality: function() {
            const self = this;
            
            const scopeSelector = '.sffc-premium-article-wrapper';

            const isInsideResultsContext = ($element) => {
                return $element.closest('.sffc-pe-results-container').length > 0;
            };

            // Handle mode switching (only inside premium article blocks)
            $(document).on('click', `${scopeSelector} .sffc-mode-tab`, function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const $container = $tab.closest('.sffc-compact-search-container');

                if (isInsideResultsContext($container)) {
                    return;
                }
                const $input = $container.find('.sffc-search-input');
                const mode = $tab.data('mode');
                const placeholder = $tab.data('placeholder');
                
                // Update active state
                $tab.siblings('.sffc-mode-tab').removeClass('active').attr('aria-pressed', 'false');
                $tab.addClass('active').attr('aria-pressed', 'true');
                
                // Update input
                $input.attr('placeholder', placeholder).data('mode', mode);
                $container.attr('data-active-mode', mode);
                
                // If there's a current search, trigger new search with updated mode
                if ($input.val().trim()) {
                    self.performSearch($input.val().trim(), mode);
                }
            });
            
            // Handle search input
            $(document).on('input', `${scopeSelector} .sffc-search-input`, function() {
                const $input = $(this);
                const $container = $input.closest('.sffc-compact-search-container');

                if (isInsideResultsContext($container)) {
                    return;
                }
                const $clearBtn = $container.find('.sffc-search-clear');
                
                // Show/hide clear button
                if ($input.val().trim()) {
                    $clearBtn.removeAttr('hidden');
                } else {
                    $clearBtn.attr('hidden', true);
                }
            });
            
            // Handle search submission
            $(document).on('keydown', `${scopeSelector} .sffc-search-input`, function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const $input = $(this);
                    const query = $input.val().trim();
                    const mode = $input.data('mode');
                    if (isInsideResultsContext($input.closest('.sffc-compact-search-container'))) {
                        return;
                    }
                    
                    if (query) {
                        self.performSearch(query, mode);
                    }
                }
            });
            
            // Handle search button click
            $(document).on('click', `${scopeSelector} .sffc-search-submit`, function(e) {
                e.preventDefault();
                const $container = $(this).closest('.sffc-compact-search-container');
                if (isInsideResultsContext($container)) {
                    return;
                }
                const $input = $container.find('.sffc-search-input');
                const query = $input.val().trim();
                const mode = $input.data('mode');
                
                if (query) {
                    self.performSearch(query, mode);
                }
            });
            
            // Handle clear button
            $(document).on('click', `${scopeSelector} .sffc-search-clear`, function(e) {
                e.preventDefault();
                const $container = $(this).closest('.sffc-compact-search-container');
                if (isInsideResultsContext($container)) {
                    return;
                }
                const $input = $container.find('.sffc-search-input');
                
                $input.val('').focus();
                $(this).attr('hidden', true);
            });
            
            // Focus input on search icon click
            $(document).on('click', `${scopeSelector} .sffc-search-icon`, function() {
                const $container = $(this).closest('.sffc-compact-search-container');
                if (isInsideResultsContext($container)) {
                    return;
                }
                $container.find('.sffc-search-input').focus();
            });
        },
        
        // Perform search and redirect to results page
        performSearch: function(query, mode) {
            if (!query || typeof sffcPremiumArticle === 'undefined') return;
            
            // Construct search URL with parameters
            const searchUrl = new URL(sffcPremiumArticle.searchUrl, window.location.origin);
            searchUrl.searchParams.set('q', query);
            searchUrl.searchParams.set('mode', mode);
            searchUrl.searchParams.set('cache_bust', Date.now()); // Bypass caching
            
            // Add analytics tracking if available
            if (typeof gtag === 'function') {
                gtag('event', 'search', {
                    search_term: query,
                    search_mode: mode,
                    event_category: 'premium_article_search'
                });
            }
            
            // Redirect to search results page
            window.location.href = searchUrl.toString();
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        SFFCPremiumArticle.init();
    });
    
    // Add easing function for smooth scrolling
    $.easing.easeInOutQuart = function (x, t, b, c, d) {
        if ((t/=d/2) < 1) return c/2*t*t*t*t + b;
        return -c/2 * ((t-=2)*t*t*t - 2) + b;
    };
    
})(jQuery);
