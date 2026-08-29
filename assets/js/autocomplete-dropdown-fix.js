/**
 * Autocomplete Dropdown Positioning Fix
 * Ensures dropdown appears properly and is not clipped
 */

(function($) {
    'use strict';

    // Enhanced dropdown positioning
    function ensureDropdownVisibility() {
        $('.sffc-autocomplete-dropdown').each(function() {
            const dropdown = $(this);
            const container = dropdown.closest('.sffc-search-bar-container');
            
            if (dropdown.is(':visible')) {
                // Add visibility class for CSS animation
                dropdown.addClass('sffc-dropdown-visible');
                
                // Ensure parent containers don't clip the dropdown
                container.add(container.parents()).css({
                    'overflow': 'visible',
                    'z-index': 'auto'
                });
                
                // Position dropdown relative to the search bar
                const searchBar = container.find('.sffc-search-bar');
                const searchBarRect = searchBar[0].getBoundingClientRect();
                const containerRect = container[0].getBoundingClientRect();
                
                // Calculate optimal positioning
                const viewportHeight = $(window).height();
                const dropdownHeight = dropdown.outerHeight();
                const spaceBelow = viewportHeight - searchBarRect.bottom;
                const spaceAbove = searchBarRect.top;
                
                // Position dropdown below search bar by default
                let topPosition = '100%';
                let marginTop = '8px';
                
                // If not enough space below and more space above, position above
                if (spaceBelow < dropdownHeight + 20 && spaceAbove > dropdownHeight + 20) {
                    topPosition = 'auto';
                    dropdown.css({
                        'bottom': '100%',
                        'top': 'auto',
                        'margin-top': '0',
                        'margin-bottom': '8px'
                    });
                } else {
                    dropdown.css({
                        'top': topPosition,
                        'bottom': 'auto',
                        'margin-top': marginTop,
                        'margin-bottom': '0'
                    });
                }
                
                // Ensure dropdown width matches search bar
                const searchBarWidth = searchBar.outerWidth();
                dropdown.css({
                    'width': searchBarWidth + 'px',
                    'min-width': '300px',
                    'max-width': '100vw'
                });
                
                // For mobile, ensure dropdown doesn't extend beyond viewport
                if ($(window).width() <= 768) {
                    const leftOffset = containerRect.left;
                    const rightOffset = $(window).width() - (containerRect.left + containerRect.width);
                    
                    if (leftOffset < 10) {
                        dropdown.css('margin-left', (10 - leftOffset) + 'px');
                    }
                    if (rightOffset < 10) {
                        dropdown.css('margin-right', (10 - rightOffset) + 'px');
                    }
                }
            } else {
                dropdown.removeClass('sffc-dropdown-visible');
            }
        });
    }

    // Override the original showAutocomplete and hideAutocomplete methods
    function enhanceDropdownMethods() {
        // Wait for PE Search Interface to be initialized
        setTimeout(function() {
            if (window.PESearchInterface) {
                const originalShowAutocomplete = window.PESearchInterface.prototype.showAutocomplete;
                const originalHideAutocomplete = window.PESearchInterface.prototype.hideAutocomplete;
                
                // Enhanced showAutocomplete
                window.PESearchInterface.prototype.showAutocomplete = function() {
                    if (originalShowAutocomplete) {
                        originalShowAutocomplete.call(this);
                    } else {
                        this.autocompleteDropdown.show();
                    }
                    
                    // Apply positioning fixes
                    setTimeout(ensureDropdownVisibility, 10);
                };
                
                // Enhanced hideAutocomplete
                window.PESearchInterface.prototype.hideAutocomplete = function() {
                    this.autocompleteDropdown.removeClass('sffc-dropdown-visible');
                    
                    setTimeout(() => {
                        if (originalHideAutocomplete) {
                            originalHideAutocomplete.call(this);
                        } else {
                            this.autocompleteDropdown.hide();
                        }
                    }, 150);
                    
                    this.selectedSuggestionIndex = -1;
                };
            }
        }, 100);
    }

    // Fallback method using jQuery events
    function setupDropdownEventListeners() {
        $(document).on('DOMNodeInserted', '.sffc-autocomplete-dropdown', function() {
            setTimeout(ensureDropdownVisibility, 10);
        });
        
        // Watch for visibility changes
        $(document).on('show', '.sffc-autocomplete-dropdown', function() {
            setTimeout(ensureDropdownVisibility, 10);
        });
        
        // Monitor for dynamic content changes
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    const dropdown = $(mutation.target).closest('.sffc-autocomplete-dropdown');
                    if (dropdown.length && dropdown.is(':visible')) {
                        setTimeout(ensureDropdownVisibility, 10);
                    }
                }
            });
        });
        
        // Observe autocomplete content changes
        $('.sffc-autocomplete-content').each(function() {
            observer.observe(this, {
                childList: true,
                subtree: true
            });
        });
    }

    // Responsive handling
    function handleResize() {
        if ($('.sffc-autocomplete-dropdown:visible').length) {
            setTimeout(ensureDropdownVisibility, 10);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Load additional CSS
        const cssLink = $('<link>').attr({
            rel: 'stylesheet',
            type: 'text/css',
            href: sffc_search.plugin_url + 'assets/css/autocomplete-dropdown-fix.css'
        });
        $('head').append(cssLink);
        
        // Setup event listeners
        setupDropdownEventListeners();
        enhanceDropdownMethods();
        
        // Handle window resize
        $(window).on('resize', $.debounce(250, handleResize));
        
        // Initial check
        setTimeout(ensureDropdownVisibility, 500);
    });

    // Expose for debugging
    window.ensureDropdownVisibility = ensureDropdownVisibility;

})(jQuery);

// jQuery debounce utility (if not already available)
if (!jQuery.debounce) {
    jQuery.debounce = function(delay, fn) {
        let timeoutId;
        return function(...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delay);
        };
    };
}