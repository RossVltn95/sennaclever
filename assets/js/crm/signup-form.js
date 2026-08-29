(function($) {
    'use strict';

    $(document).ready(function() {
        var config = window.sffcSignupFormConfig || {};

        // Handle email check (Step 1 → Step 2 or 3)
        $('[data-signup-check-email]').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $form = $btn.closest('[data-signup-form]');
            var $step1 = $form.find('[data-signup-step="1"]');
            var $step2 = $form.find('[data-signup-step="2"]');
            var $step3 = $form.find('[data-signup-step="3"]');
            var $emailInput = $form.find('#sffc-signup-email');
            var email = $emailInput.val().trim();

            // Validate email
            if (!email || !isValidEmail(email)) {
                showFieldError($emailInput, 'Please enter a valid email address.');
                return;
            }

            // Clear any previous errors
            clearFieldError($emailInput);

            // Disable button and show loading state
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Checking...');

            // Check if email exists via AJAX
            $.ajax({
                url: config.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_crm_check_user_email',
                    nonce: config.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.exists) {
                            // User exists - show step 3 (welcome back)
                            $step1.attr('hidden', true);
                            $step2.attr('hidden', true);
                            $step3.removeAttr('hidden');

                            // Update welcome message with user's name if available
                            if (response.data.user_name) {
                                $step3.find('h3').text('Welcome Back, ' + response.data.user_name + '!');
                            }

                            // Display the email address
                            $step3.find('[data-user-email]').text(email);
                        } else {
                            // New user - show step 2 (full registration form)
                            // Pre-fill email in step 2
                            $form.find('#sffc-signup-email-confirm').val(email);

                            $step1.attr('hidden', true);
                            $step2.removeAttr('hidden');
                            $step3.attr('hidden', true);

                            // Focus on first name field
                            $form.find('#sffc-signup-first-name').focus();
                        }
                    } else {
                        showFieldError($emailInput, response.data.message || 'Unable to verify email. Please try again.');
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    showFieldError($emailInput, 'Something went wrong. Please try again.');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Handle full registration form submission (Step 2)
        $('[data-signup-form]').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $form.find('[data-signup-submit]');
            var $feedback = $form.find('[data-signup-feedback]');
            var firstName = $form.find('#sffc-signup-first-name').val().trim();
            var lastName = $form.find('#sffc-signup-last-name').val().trim();
            var email = $form.find('#sffc-signup-email-confirm').val().trim();
            var password = $form.find('#sffc-signup-password').val();
            var terms = $form.find('#sffc-signup-terms').is(':checked');

            // Clear previous feedback
            if ($feedback.length) {
                $feedback.removeClass('is-error is-success').attr('hidden', true);
            }

            // Validate
            var errors = [];

            if (!firstName) {
                errors.push({ field: '#sffc-signup-first-name', message: 'First name is required.' });
            }

            if (!lastName) {
                errors.push({ field: '#sffc-signup-last-name', message: 'Last name is required.' });
            }

            if (!email || !isValidEmail(email)) {
                errors.push({ field: '#sffc-signup-email-confirm', message: 'Valid email is required.' });
            }

            if (!password) {
                errors.push({ field: '#sffc-signup-password', message: 'Password is required.' });
            } else if (password.length < 8) {
                errors.push({ field: '#sffc-signup-password', message: 'Password must be at least 8 characters.' });
            }

            if (!terms) {
                errors.push({ field: '#sffc-signup-terms', message: 'You must agree to the terms and privacy policy.' });
            }

            if (errors.length > 0) {
                // Show first error
                var firstError = errors[0];
                var $field = $form.find(firstError.field);
                showFieldError($field, firstError.message);
                $field.focus();
                return;
            }

            // Disable button and show loading state
            var originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Continuing...');

            // Submit via AJAX
            $.ajax({
                url: config.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'sffc_crm_register_user',
                    nonce: config.nonce,
                    first_name: firstName,
                    last_name: lastName,
                    email: email,
                    password: password,
                    terms: terms
                },
                success: function(response) {
                    if (response.success) {
                        if ($feedback.length) {
                            $feedback
                                .addClass('is-success')
                                .text('Opening memberships...')
                                .removeAttr('hidden');
                        }

                        // Redirect to the logged-in default tab.
                        setTimeout(function() {
                            window.location.href = response.data.redirect || config.redirectUrl || '/memberships/';
                        }, 1000);
                    } else {
                        if ($feedback.length) {
                            $feedback
                                .addClass('is-error')
                                .text(response.data.message || 'Registration failed. Please try again.')
                                .removeAttr('hidden');
                        }
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    if ($feedback.length) {
                        $feedback
                            .addClass('is-error')
                            .text('Something went wrong. Please try again.')
                            .removeAttr('hidden');
                    }
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Helper function to validate email
        function isValidEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Helper function to show field error
        function showFieldError($field, message) {
            // Remove any existing error
            clearFieldError($field);

            // Add error class to field
            $field.addClass('has-error');

            // Add error message
            var $error = $('<p class="sffc-signup-field-error">' + message + '</p>');
            $field.closest('.sffc-signup-form-field').append($error);
        }

        // Helper function to clear field error
        function clearFieldError($field) {
            $field.removeClass('has-error');
            $field.closest('.sffc-signup-form-field').find('.sffc-signup-field-error').remove();
        }

        // Clear errors on input
        $('[data-signup-form]').on('input', 'input', function() {
            clearFieldError($(this));
        });
    });

})(jQuery);
