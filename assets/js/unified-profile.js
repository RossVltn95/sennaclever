/**
 * Unified Profile JavaScript
 * Handles profile form interactions and saving
 */

(function($) {
    'use strict';

    const UnifiedProfile = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Option buttons (work preference, relocation, etc.)
            $(document).on('click', '.sffc-option-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const $group = $btn.closest('.sffc-option-buttons');

                // Deselect all in group
                $group.find('.sffc-option-btn').removeClass('active');

                // Select this one
                $btn.addClass('active');
            });

            // Save profile button
            $('#sffc-save-profile').on('click', function(e) {
                e.preventDefault();
                UnifiedProfile.saveProfile();
            });

            // Auto-save on field change (debounced)
            let saveTimeout;
            $(document).on('change', '.sffc-profile-input, .sffc-profile-select, .sffc-profile-textarea, .sffc-checkbox-group input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
                    UnifiedProfile.saveProfile(true); // silent save
                }, 2000);
            });
        },

        collectFormData: function() {
            const data = {
                action: 'sffc_save_audit_profile',
                nonce: sffc_profile.nonce
            };

            // Text inputs
            $('.sffc-profile-input').each(function() {
                const field = $(this).data('field') || $(this).attr('name');
                if (field) {
                    data[field] = $(this).val();
                }
            });

            // Selects
            $('.sffc-profile-select').each(function() {
                const field = $(this).data('field') || $(this).attr('name');
                if (field) {
                    data[field] = $(this).val();
                }
            });

            // Textareas
            $('.sffc-profile-textarea').each(function() {
                const field = $(this).data('field') || $(this).attr('name');
                if (field) {
                    data[field] = $(this).val();
                }
            });

            // Option buttons (get active value)
            $('.sffc-option-buttons').each(function() {
                const field = $(this).data('field');
                const activeBtn = $(this).find('.sffc-option-btn.active');
                if (field && activeBtn.length) {
                    data[field] = activeBtn.data('value');
                }
            });

            // Checkboxes (target industries)
            const targetIndustries = [];
            $('input[name="target_industries[]"]:checked').each(function() {
                targetIndustries.push($(this).val());
            });
            data.target_industries = targetIndustries;

            return data;
        },

        saveProfile: function(silent) {
            const $btn = $('#sffc-save-profile');
            const originalText = $btn.html();

            if (!silent) {
                $btn.prop('disabled', true).html(
                    '<svg class="sffc-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="30 60"/></svg> Saving...'
                );
            }

            const formData = this.collectFormData();

            $.post(sffc_profile.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        if (!silent) {
                            $btn.html(
                                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Saved!'
                            );

                            // Update completion ring
                            if (response.data.completion_percentage !== undefined) {
                                $('.sffc-completion-text').text(response.data.completion_percentage + '%');
                                $('.sffc-ring-fill').attr('stroke-dasharray', response.data.completion_percentage + ', 100');
                            }

                            setTimeout(function() {
                                $btn.prop('disabled', false).html(originalText);
                            }, 2000);
                        }
                    } else {
                        if (!silent) {
                            $btn.prop('disabled', false).html(originalText);
                            alert('Failed to save profile: ' + (response.data.message || 'Unknown error'));
                        }
                    }
                })
                .fail(function() {
                    if (!silent) {
                        $btn.prop('disabled', false).html(originalText);
                        alert('Failed to save profile. Please try again.');
                    }
                });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.sffc-unified-profile').length) {
            UnifiedProfile.init();
        }
    });

    // Add spinner animation
    $('<style>')
        .text('@keyframes sffc-spin { to { transform: rotate(360deg); } } .sffc-spinner { animation: sffc-spin 1s linear infinite; }')
        .appendTo('head');

})(jQuery);
