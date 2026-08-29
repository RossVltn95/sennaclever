/**
 * MENA Careers Input Field JavaScript
 * Handles form submission and user interaction
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initSennaInputFields();
    });
    
    /**
     * Initialize all MENA Careers input fields on the page
     */
    function initSennaInputFields() {
        $('.sffc-senna-input-form').each(function() {
            const $form = $(this);
            const $input = $form.find('.sffc-senna-input');
            const $button = $form.find('.sffc-senna-submit');
            const $loading = $form.find('.sffc-input-loading');
            const $error = $form.find('.sffc-input-error');

            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }

            $button.prop('disabled', $input.val().trim().length === 0);
            
            // Handle form submission
            $form.on('submit', function(e) {
                e.preventDefault();
                handleFormSubmission($form, $input, $button, $loading, $error);
            });
            
            // Handle Enter key
            $input.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $form.trigger('submit');
                }
            });
            
            // Auto-focus and enhance UX
            $input.on('focus', function() {
                $form.closest('.sffc-senna-input-container').addClass('focused');
            });
            
            $input.on('blur', function() {
                $form.closest('.sffc-senna-input-container').removeClass('focused');
            });
            
            // Real-time validation
            $input.on('input', function() {
                const query = $(this).val().trim();
                $button.prop('disabled', query.length === 0);
                
                if (query.length > 0) {
                    $error.hide();
                }
            });
        });
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmission($form, $input, $button, $loading, $error) {
        const query = $input.val().trim();
        
        // Validate input
        if (query.length === 0) {
            showError($error, 'Please enter a question before submitting.');
            $input.focus();
            return;
        }
        
        if (query.length < 3) {
            showError($error, 'Please enter a more detailed question.');
            $input.focus();
            return;
        }
        
        // Show loading state
        setLoadingState($button, $loading, true);
        $error.hide();
        
        // Prepare form data
        const formData = {
            action: 'sffc_senna_input_query',
            senna_query: query,
            post_id: $form.find('input[name="post_id"]').val(),
            post_title: $form.find('input[name="post_title"]').val(),
            post_content: $form.find('input[name="post_content"]').val(),
            include_content: $form.find('input[name="include_content"]').val(),
            include_title: $form.find('input[name="include_title"]').val(),
            redirect_url: $form.find('input[name="redirect_url"]').val(),
            nonce: $form.find('input[name="nonce"]').val()
        };
        
        // Submit via AJAX
        $.ajax({
            url: senna_input_ajax.ajax_url,
            type: 'POST',
            data: formData,
            timeout: 15000,
            success: function(response) {
                setLoadingState($button, $loading, false);
                
                if (response.success) {
                    handleSuccessfulSubmission(response.data, $form, $input);
                } else {
                    const errorMessage = response.data?.message || 'An error occurred while processing your question.';
                    showError($error, errorMessage);
                }
            },
            error: function(xhr, status, error) {
                setLoadingState($button, $loading, false);
                
                let errorMessage = 'Unable to process your question. Please try again.';
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'Access denied. Please refresh the page and try again.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error. Please try again later.';
                }
                
                showError($error, errorMessage);
            }
        });
    }
    
    /**
     * Handle successful form submission
     */
    function handleSuccessfulSubmission(data, $form, $input) {
        const redirectUrl = data.redirect_url;
        
        if (redirectUrl) {
            // Show success message briefly before redirect
            showSuccessMessage($form, 'Redirecting to MENA Careers chat...');
            
            // Clear the input
            $input.val('');
            
            // Redirect after a short delay
            setTimeout(function() {
                window.location.href = redirectUrl;
            }, 1000);
        } else {
            showError($form.find('.sffc-input-error'), 'No redirect URL provided.');
        }
    }
    
    /**
     * Set loading state
     */
    function setLoadingState($button, $loading, isLoading) {
        if (isLoading) {
            if (!$button.data('original-text')) {
                $button.data('original-text', $button.text());
            }

            $button.prop('disabled', true).text('Processing...');
            $loading.show();
        } else {
            const originalText = $button.data('original-text') || 'Ask MENA Careers';
            $button.prop('disabled', false).text(originalText);
            $loading.hide();
        }
    }
    
    /**
     * Show error message
     */
    function showError($error, message) {
        $error.html('<strong>Error:</strong> ' + message).show();
        
        // Auto-hide after 8 seconds
        setTimeout(function() {
            $error.fadeOut();
        }, 8000);
    }
    
    /**
     * Show success message
     */
    function showSuccessMessage($form, message) {
        $form.find('.sffc-input-success').remove();
        const $success = $('<div class="sffc-input-success"></div>')
            .html('<strong>Success:</strong> ' + message)
            .css({
                'padding': '12px',
                'background': '#d4edda',
                'color': '#155724',
                'border-radius': '8px',
                'margin-top': '8px',
                'font-size': '14px',
                'display': 'none'
            });
        
        $form.append($success);
        $success.fadeIn();
    }
    
    /**
     * Enhanced UX features
     */
    
    // Auto-resize for better mobile experience
    function enhanceForMobile() {
        if (window.innerWidth <= 768) {
            $('.sffc-senna-input').attr('autocomplete', 'off');
        }
    }
    
    // Initialize mobile enhancements
    enhanceForMobile();
    $(window).on('resize', enhanceForMobile);
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + / to focus on input field
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            const $input = $('.sffc-senna-input:visible:first');
            if ($input.length) {
                $input.focus();
            }
        }
    });
    
    // Add helpful placeholder animations
    function animatePlaceholder() {
        $('.sffc-senna-input').each(function() {
            const $input = $(this);
            const originalPlaceholder = $input.attr('placeholder');
            const examples = [
                'Ask MENA Careers about this content...',
                'What are the key insights here?',
                'How does this relate to my career?',
                'Can you explain this in simple terms?',
                'What should I know about this topic?'
            ];
            
            let currentIndex = 0;
            
            $input.on('focus', function() {
                clearInterval($input.data('placeholder-interval'));
            });
            
            $input.on('blur', function() {
                if ($input.val().length === 0) {
                    const interval = setInterval(function() {
                        $input.attr('placeholder', examples[currentIndex]);
                        currentIndex = (currentIndex + 1) % examples.length;
                    }, 3000);
                    
                    $input.data('placeholder-interval', interval);
                }
            });
        });
    }
    
    // Initialize placeholder animations after a delay
    setTimeout(animatePlaceholder, 2000);
    
})(jQuery);
