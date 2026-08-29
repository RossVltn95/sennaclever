/**
 * CRM Job Preferences & Matches
 *
 * Handles:
 * - Profile preference editing (tags, salary, text fields)
 * - Saving/loading preferences via AJAX
 * - Matches feed pagination
 * - Navigation between tabs
 *
 * @package SennaCareers
 * @since 7.1.0
 */

(function($) {
    'use strict';

    const JobPreferences = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadInitialPreferences();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Tag add button
            $(document).on('click', '.sffc-crm-tag-add', function(e) {
                e.preventDefault();
                self.handleTagAdd($(this));
            });

            // Tag remove button
            $(document).on('click', '.sffc-crm-tag-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleTagRemove($(this));
            });

            // Field edit button
            $(document).on('click', '.sffc-crm-field-edit-btn', function(e) {
                e.preventDefault();
                self.handleFieldEdit($(this));
            });

            // Save preferences button
            $(document).on('click', '.sffc-crm-section-save-btn', function(e) {
                e.preventDefault();
                self.savePreferences();
            });

            // Go to profile button
            $(document).on('click', '.sffc-crm-goto-profile', function(e) {
                e.preventDefault();
                const targetTab = $(this).data('tab');
                self.navigateToTab(targetTab);
            });

            // Edit preferences link
            $(document).on('click', '.sffc-crm-edit-preferences-link', function(e) {
                e.preventDefault();
                const targetTab = $(this).data('tab');
                self.navigateToTab(targetTab);
            });

            // Load more matches
            $(document).on('click', '.sffc-crm-load-more-matches', function(e) {
                e.preventDefault();
                self.loadMoreMatches($(this));
            });

            // Detect changes to show save button
            $(document).on('input change', '.sffc-crm-profile-section input, .sffc-crm-profile-section select', function() {
                self.showSaveButton();
            });

            // Handle Enter key in tag input
            $(document).on('keydown', '.sffc-crm-tag-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.confirmTagAdd($(this));
                }
                if (e.key === 'Escape') {
                    $(this).remove();
                }
            });

            // Handle blur on tag input
            $(document).on('blur', '.sffc-crm-tag-input', function() {
                const $input = $(this);
                setTimeout(function() {
                    if ($input.length && document.activeElement !== $input[0]) {
                        $input.remove();
                    }
                }, 200);
            });

            // Handle Enter/Escape in text field inputs
            $(document).on('keydown', '.sffc-crm-field-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.confirmFieldEdit($(this));
                }
                if (e.key === 'Escape') {
                    self.cancelFieldEdit($(this));
                }
            });

            // Handle blur on field input
            $(document).on('blur', '.sffc-crm-field-input', function() {
                const $input = $(this);
                setTimeout(function() {
                    if ($input.is(':visible')) {
                        self.confirmFieldEdit($input);
                    }
                }, 200);
            });

            // Handle salary editor changes
            $(document).on('change', '.sffc-crm-salary-min, .sffc-crm-salary-max, .sffc-crm-salary-currency', function() {
                self.updateSalaryDisplay($(this).closest('.sffc-crm-profile-field'));
                self.showSaveButton();
            });
        },

        /**
         * Load initial preferences (if needed)
         */
        loadInitialPreferences: function() {
            // Preferences are already loaded server-side
            // This method is here for future AJAX loading if needed
        },

        /**
         * Handle tag add button click
         */
        handleTagAdd: function($btn) {
            const $container = $btn.closest('.sffc-crm-tags');
            const fieldName = $container.data('field-name');
            const options = $btn.data('options');

            // Remove existing input if any
            $container.find('.sffc-crm-tag-input-wrapper').remove();

            if (options && typeof options === 'object') {
                // Dropdown select for predefined options
                this.showOptionsDropdown($btn, options, fieldName);
            } else {
                // Text input for free-form tags
                this.showTagInput($btn);
            }
        },

        /**
         * Show dropdown for selecting from predefined options
         */
        showOptionsDropdown: function($btn, options, fieldName) {
            const $container = $btn.closest('.sffc-crm-tags');

            // Get already selected values
            const selectedValues = [];
            $container.find('.sffc-crm-tag[data-value]').each(function() {
                selectedValues.push($(this).data('value'));
            });

            // Build select element
            let $select = $('<select class="sffc-crm-tag-select">');
            $select.append('<option value="">' + 'Select...' + '</option>');

            $.each(options, function(value, label) {
                if (selectedValues.indexOf(value) === -1) {
                    $select.append('<option value="' + value + '">' + label + '</option>');
                }
            });

            const $wrapper = $('<span class="sffc-crm-tag-input-wrapper"></span>');
            $wrapper.append($select);
            $btn.before($wrapper);

            $select.focus();

            // Handle selection
            $select.on('change', function() {
                const value = $(this).val();
                if (value) {
                    const label = $(this).find('option:selected').text();
                    const $tag = $('<span class="sffc-crm-tag" data-value="' + value + '">' +
                        label +
                        '<button type="button" class="sffc-crm-tag-remove" aria-label="Remove">&times;</button>' +
                        '</span>');
                    $wrapper.before($tag);
                    $wrapper.remove();
                    JobPreferences.showSaveButton();
                }
            });

            // Handle blur
            $select.on('blur', function() {
                setTimeout(function() {
                    $wrapper.remove();
                }, 200);
            });
        },

        /**
         * Show text input for free-form tag entry
         */
        showTagInput: function($btn) {
            const $input = $('<input type="text" class="sffc-crm-tag-input" placeholder="Type and press Enter">');
            const $wrapper = $('<span class="sffc-crm-tag-input-wrapper"></span>');
            $wrapper.append($input);
            $btn.before($wrapper);
            $input.focus();
        },

        /**
         * Confirm and add tag from input
         */
        confirmTagAdd: function($input) {
            const value = $input.val().trim();
            if (value) {
                const $tag = $('<span class="sffc-crm-tag">' +
                    this.escapeHtml(value) +
                    '<button type="button" class="sffc-crm-tag-remove" aria-label="Remove">&times;</button>' +
                    '</span>');
                $input.closest('.sffc-crm-tag-input-wrapper').before($tag);
                $input.closest('.sffc-crm-tag-input-wrapper').remove();
                this.showSaveButton();
            } else {
                $input.closest('.sffc-crm-tag-input-wrapper').remove();
            }
        },

        /**
         * Handle tag remove
         */
        handleTagRemove: function($btn) {
            $btn.closest('.sffc-crm-tag').remove();
            this.showSaveButton();
        },

        /**
         * Handle field edit button click
         */
        handleFieldEdit: function($btn) {
            const $field = $btn.closest('.sffc-crm-profile-field');
            const fieldType = $field.find('.sffc-crm-field-value').data('field-type');

            if (fieldType === 'salary') {
                // Show salary editor
                $field.find('.sffc-crm-field-value').hide();
                $field.find('.sffc-crm-salary-editor').show();
                $field.find('.sffc-crm-salary-min').focus();
            } else {
                // Show text input
                const $display = $field.find('.sffc-crm-field-value');
                const $input = $field.find('.sffc-crm-field-input');
                $display.hide();
                $input.show().focus();
            }
        },

        /**
         * Confirm field edit
         */
        confirmFieldEdit: function($input) {
            const $field = $input.closest('.sffc-crm-profile-field');
            const $display = $field.find('.sffc-crm-field-value');
            const value = $input.val().trim();

            if (value) {
                // Update display text (keep the edit button)
                const $editBtn = $display.find('.sffc-crm-field-edit-btn');
                $display.contents().filter(function() {
                    return this.nodeType === 3; // Text nodes
                }).remove();
                $display.prepend(document.createTextNode(value));
            } else {
                // Restore "Not set" text
                const $editBtn = $display.find('.sffc-crm-field-edit-btn');
                $display.contents().filter(function() {
                    return this.nodeType === 3;
                }).remove();
                $display.prepend(document.createTextNode('Not set'));
            }

            $input.hide();
            $display.show();
            this.showSaveButton();
        },

        /**
         * Cancel field edit
         */
        cancelFieldEdit: function($input) {
            const $field = $input.closest('.sffc-crm-profile-field');
            const $display = $field.find('.sffc-crm-field-value');
            $input.hide();
            $display.show();
        },

        /**
         * Update salary display
         */
        updateSalaryDisplay: function($field) {
            const min = $field.find('.sffc-crm-salary-min').val();
            const max = $field.find('.sffc-crm-salary-max').val();
            const currency = $field.find('.sffc-crm-salary-currency').val();

            const symbols = {
                'GBP': '£',
                'USD': '$',
                'EUR': '€',
                'CHF': 'CHF '
            };
            const symbol = symbols[currency] || currency + ' ';

            let displayText = 'Not set';
            if (min && max) {
                displayText = symbol + parseInt(min).toLocaleString() + ' - ' + symbol + parseInt(max).toLocaleString();
            } else if (min) {
                displayText = symbol + parseInt(min).toLocaleString() + '+';
            } else if (max) {
                displayText = 'Up to ' + symbol + parseInt(max).toLocaleString();
            }

            const $display = $field.find('.sffc-crm-field-value');
            const $editBtn = $display.find('.sffc-crm-field-edit-btn');
            $display.contents().filter(function() {
                return this.nodeType === 3;
            }).remove();
            $display.prepend(document.createTextNode(displayText));
        },

        /**
         * Show save button
         */
        showSaveButton: function() {
            $('.sffc-crm-section-save-btn').show();
        },

        /**
         * Gather preferences from form
         */
        gatherPreferences: function() {
            const prefs = {};

            // Gather tags
            $('.sffc-crm-tags-editable').each(function() {
                const fieldName = $(this).data('field-name');
                const values = [];

                $(this).find('.sffc-crm-tag').each(function() {
                    const dataValue = $(this).data('value');
                    if (dataValue) {
                        // Use data-value for select options (sectors, seniority, etc.)
                        values.push(dataValue);
                    } else {
                        // Use text content for free-form tags
                        const text = $(this).contents().filter(function() {
                            return this.nodeType === 3;
                        }).text().trim();
                        if (text) {
                            values.push(text);
                        }
                    }
                });

                prefs[fieldName] = values;
            });

            // Gather salary
            prefs.salary_min = parseInt($('.sffc-crm-salary-min').val()) || 0;
            prefs.salary_max = parseInt($('.sffc-crm-salary-max').val()) || 0;
            prefs.salary_currency = $('.sffc-crm-salary-currency').val() || 'GBP';

            // Gather text fields
            $('[data-field="internship_duration"] .sffc-crm-field-input').each(function() {
                prefs.internship_duration = $(this).val() || '';
            });
            $('[data-field="start_date_pref"] .sffc-crm-field-input').each(function() {
                prefs.start_date_pref = $(this).val() || '';
            });

            return prefs;
        },

        /**
         * Save preferences via AJAX
         */
        savePreferences: function() {
            const self = this;
            const $btn = $('.sffc-crm-section-save-btn');
            const prefs = this.gatherPreferences();

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: sffcCrm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_save_job_preferences',
                    nonce: sffcCrm.nonce,
                    ...prefs
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Saved!');
                        setTimeout(function() {
                            $btn.hide().text('Save');
                        }, 2000);

                        // Show success notification
                        self.showNotification('Preferences saved successfully', 'success');

                        // Refresh matches tab if it's visible
                        if ($('[data-panel="matches"]').hasClass('active')) {
                            self.refreshMatchesTab();
                        }
                    } else {
                        $btn.prop('disabled', false).text('Save');
                        self.showNotification('Failed to save preferences', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Save');
                    self.showNotification('Failed to save preferences', 'error');
                }
            });
        },

        /**
         * Navigate to a different tab
         */
        navigateToTab: function(tabName) {
            const $tab = $('[data-tab="' + tabName + '"]');
            if ($tab.length) {
                $tab.click();
                // Scroll to top
                $('html, body').animate({
                    scrollTop: $('.sffc-crm-profile-container').offset().top - 100
                }, 300);
            }
        },

        /**
         * Refresh matches tab (reload page for now)
         */
        refreshMatchesTab: function() {
            // For simplicity, reload the page to show updated matches
            // In production, you might want to load matches via AJAX
            window.location.reload();
        },

        /**
         * Load more matches
         */
        loadMoreMatches: function($btn) {
            const currentPage = parseInt($btn.data('page'));
            const nextPage = currentPage + 1;

            $btn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: sffcCrm.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_crm_get_preference_matches',
                    nonce: sffcCrm.nonce,
                    page: nextPage,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success && response.data.posts && response.data.posts.length > 0) {
                        // Append new posts to feed
                        // Note: This would require rendering posts on the client side
                        // For now, we'll just reload the page
                        // In production, you'd want to implement client-side rendering

                        $btn.data('page', nextPage);
                        $btn.prop('disabled', false).text('Load More Matches');

                        if (!response.data.has_more) {
                            $btn.hide();
                        }

                        // For now, show a message
                        JobPreferences.showNotification('Please refresh to see more matches', 'info');
                    } else {
                        $btn.hide();
                        JobPreferences.showNotification('No more matches found', 'info');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Load More Matches');
                    JobPreferences.showNotification('Failed to load more matches', 'error');
                }
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            // Simple notification using alert for now
            // In production, use a toast/notification library
            if (type === 'error') {
                alert('Error: ' + message);
            } else if (type === 'success') {
                // Show brief success message
                const $notice = $('<div class="sffc-crm-notice sffc-crm-notice-success">' + message + '</div>');
                $('body').append($notice);
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        JobPreferences.init();
    });

})(jQuery);
