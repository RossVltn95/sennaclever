/**
 * Floating Search Icon
 * Toggles the message search bar visibility
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Create floating search icon
        const floatingIcon = $(`
            <div class="floating-search-icon" id="floating-search-icon" title="Search conversations">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </div>
        `);
        
        // Add to body
        $('body').append(floatingIcon);
        
        // Toggle search bar visibility
        $('#floating-search-icon').on('click', function() {
            const searchBar = $('.sffc-message-search');
            
            if (searchBar.hasClass('active')) {
                // Hide search bar
                searchBar.removeClass('active');
                $(this).removeClass('active');
            } else {
                // Show search bar
                searchBar.addClass('active');
                $(this).addClass('active');
                
                // Focus on search input
                setTimeout(() => {
                    $('#message-search').focus();
                }, 100);
            }
        });
        
        // Close search when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.sffc-message-search, #floating-search-icon').length) {
                $('.sffc-message-search').removeClass('active');
                $('#floating-search-icon').removeClass('active');
            }
        });
        
        // Close search on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.sffc-message-search').removeClass('active');
                $('#floating-search-icon').removeClass('active');
            }
        });
        
        // Keep existing search functionality
        $('#message-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            if (searchTerm.length > 0) {
                // Filter messages
                $('.senna-message, .user-message').each(function() {
                    const messageText = $(this).text().toLowerCase();
                    if (messageText.includes(searchTerm)) {
                        $(this).show().addClass('highlight');
                    } else {
                        $(this).hide().removeClass('highlight');
                    }
                });
                
                // Show "no results" if needed
                if ($('.senna-message:visible, .user-message:visible').length === 0) {
                    if ($('#no-search-results').length === 0) {
                        $('#senna-messages').append(`
                            <div id="no-search-results" class="no-results">
                                No messages found matching "${searchTerm}"
                            </div>
                        `);
                    }
                } else {
                    $('#no-search-results').remove();
                }
            } else {
                // Show all messages when search is cleared
                $('.senna-message, .user-message').show().removeClass('highlight');
                $('#no-search-results').remove();
            }
        });
        
        // Add keyboard shortcut (Ctrl/Cmd + K)
        $(document).on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                $('#floating-search-icon').click();
            }
        });
        
        // Style the active state
        const style = $(`
            <style>
                .floating-search-icon.active {
                    background: var(--gold, #D4AF37) !important;
                }
                
                .floating-search-icon.active svg {
                    color: var(--dark-green, #1A3028) !important;
                }
                
                .highlight {
                    background-color: rgba(212, 175, 55, 0.2) !important;
                    border-left: 3px solid var(--gold, #D4AF37) !important;
                }
                
                .no-results {
                    text-align: center;
                    padding: 40px;
                    color: #666;
                    font-style: italic;
                }
                
                /* Pulse animation for floating icon */
                @keyframes pulse {
                    0% {
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    }
                    50% {
                        box-shadow: 0 4px 20px rgba(212, 175, 55, 0.4);
                    }
                    100% {
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    }
                }
                
                .floating-search-icon {
                    animation: pulse 3s infinite;
                }
                
                .floating-search-icon:hover {
                    animation: none;
                }
            </style>
        `);
        
        $('head').append(style);
        
        console.log('Floating search icon initialized');
    });
    
})(jQuery);