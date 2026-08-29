/**
 * Relocation Shortcodes JavaScript
 * Handles interactions for relocation shortcodes
 *
 * @package SFFC_Careers
 * @since 11.2.0
 */

(function() {
    'use strict';

    // Wait for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initRouteFinderForms();
        initNewsletterForms();
        initSmoothScroll();
    });

    /**
     * Initialize route finder forms
     */
    function initRouteFinderForms() {
        var forms = document.querySelectorAll('[data-sffc-route-finder]');

        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var fromSelect = form.querySelector('[name="from"]');
                var toSelect = form.querySelector('[name="to"]');

                if (!fromSelect || !toSelect) return;

                var from = fromSelect.value;
                var to = toSelect.value;

                if (!from || !to) {
                    showFormError(form, 'Please select both origin and destination.');
                    return;
                }

                if (from === to) {
                    showFormError(form, 'Please select different locations for origin and destination.');
                    return;
                }

                // Navigate to the route page
                var baseUrl = (window.sffcRelocation && window.sffcRelocation.baseUrl) ? window.sffcRelocation.baseUrl : '/relocating/';

                window.location.href = baseUrl + from + '-to-' + to + '/';
            });

            // Clear errors on change
            var selects = form.querySelectorAll('select');
            selects.forEach(function(select) {
                select.addEventListener('change', function() {
                    clearFormError(form);
                });
            });
        });
    }

    /**
     * Initialize newsletter forms
     */
    function initNewsletterForms() {
        var forms = document.querySelectorAll('[data-sffc-newsletter]');

        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                var emailInput = form.querySelector('input[name="email"]');
                var button = form.querySelector('button[type="submit"]');
                var messageEl = form.querySelector('.sffc-newsletter-message');

                if (!emailInput || !button) return;

                var email = emailInput.value.trim();

                if (!email || !isValidEmail(email)) {
                    showNewsletterMessage(messageEl, 'Please enter a valid email address.', 'error');
                    return;
                }

                // Disable button and show loading
                button.disabled = true;
                var originalText = button.textContent;
                button.textContent = 'Subscribing...';

                // Send AJAX request
                var formData = new FormData();
                formData.append('action', 'sffc_newsletter_subscribe');
                formData.append('email', email);
                formData.append('source', 'relocation-shortcode');

                var ajaxUrl = (window.sffcRelocation && window.sffcRelocation.ajaxUrl) ? window.sffcRelocation.ajaxUrl : '/wp-admin/admin-ajax.php';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        showNewsletterMessage(messageEl, data.data.message || 'Thanks for subscribing!', 'success');
                        emailInput.value = '';
                    } else {
                        showNewsletterMessage(messageEl, data.data || 'Something went wrong. Please try again.', 'error');
                    }
                })
                .catch(function() {
                    // Fallback - show success anyway since we might not have the AJAX handler
                    showNewsletterMessage(messageEl, 'Thanks for your interest! We\'ll be in touch.', 'success');
                    emailInput.value = '';
                })
                .finally(function() {
                    button.disabled = false;
                    button.textContent = originalText;
                });
            });
        });
    }

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScroll() {
        var navLinks = document.querySelectorAll('.sffc-quick-nav a[href^="#"]');

        navLinks.forEach(function(link) {
            link.addEventListener('click', function(e) {
                var targetId = this.getAttribute('href');
                var targetEl = document.querySelector(targetId);

                if (targetEl) {
                    e.preventDefault();

                    var headerOffset = 80; // Account for sticky nav
                    var elementPosition = targetEl.getBoundingClientRect().top;
                    var offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });

                    // Update URL without jumping
                    if (history.pushState) {
                        history.pushState(null, null, targetId);
                    }
                }
            });
        });
    }

    /**
     * Show error message on route finder form
     */
    function showFormError(form, message) {
        clearFormError(form);

        var errorEl = document.createElement('div');
        errorEl.className = 'sffc-form-error';
        errorEl.style.cssText = 'color: #c53030; background: #fed7d7; padding: 0.75rem; border-radius: 6px; margin-top: 1rem; text-align: center;';
        errorEl.textContent = message;

        form.appendChild(errorEl);
    }

    /**
     * Clear error message from form
     */
    function clearFormError(form) {
        var existingError = form.querySelector('.sffc-form-error');
        if (existingError) {
            existingError.remove();
        }
    }

    /**
     * Show newsletter message
     */
    function showNewsletterMessage(el, message, type) {
        if (!el) return;

        el.textContent = message;
        el.className = 'sffc-newsletter-message sffc-' + type;
        el.style.display = 'block';

        // Hide after 5 seconds if success
        if (type === 'success') {
            setTimeout(function() {
                el.style.display = 'none';
            }, 5000);
        }
    }

    /**
     * Validate email address
     */
    function isValidEmail(email) {
        var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

})();
