(function($) {
    'use strict';

    // Professional Profile Settings Manager
    const JPMorganSettings = {
        init: function() {
            this.initNavigation();
            this.initProfileForm();
            this.initExpertiseManager();
            this.initProfilePictureUpload();
            this.initCareerPreferences();
            this.initPreferencesForm();
            this.bindEvents();
        },

        initNavigation: function() {
            $('.jp-settings-nav-item').off('click.jpmorgan').on('click.jpmorgan', function(e) {
                e.preventDefault();
                const sectionId = $(this).data('section');
                
                // Update active nav item
                $('.jp-settings-nav-item').removeClass('active');
                $(this).addClass('active');
                
                // Show target section
                $('.jp-settings-section').removeClass('active');
                $(`#${sectionId}`).addClass('active');
                
                // Update URL hash
                window.location.hash = sectionId;
            });

            // Handle initial hash
            if (window.location.hash) {
                const hash = window.location.hash.replace('#', '');
                $(`.jp-settings-nav-item[data-section="${hash}"]`).click();
            }
        },

        initProfileForm: function() {
            $('#jp-profile-form').off('submit.jpmorgan').on('submit.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.saveProfile();
            });

            $('#jp-cancel-profile').off('click.jpmorgan').on('click.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.resetProfileForm();
            });
        },

        saveProfile: function() {
            const form = $('#jp-profile-form');
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            
            submitButton.text('Saving...').prop('disabled', true);

            const formData = {
                action: 'sffc_update_professional_profile',
                nonce: sffc_ajax.nonce
            };

            // Collect form data
            form.find('input, select, textarea').each(function() {
                const field = $(this);
                if (field.attr('type') === 'checkbox') {
                    formData[field.attr('name')] = field.is(':checked') ? 1 : 0;
                } else {
                    formData[field.attr('name')] = field.val();
                }
            });

            $.post(sffc_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        JPMorganSettings.showNotification('Profile updated successfully!', 'success');
                        // Track the update
                        JPMorganSettings.trackInteraction('profile_update', {
                            fields_updated: Object.keys(formData).length - 2 // exclude action and nonce
                        });
                    } else {
                        JPMorganSettings.showNotification('Failed to update profile: ' + response.data, 'error');
                    }
                })
                .fail(function() {
                    JPMorganSettings.showNotification('Network error. Please try again.', 'error');
                })
                .always(function() {
                    submitButton.text(originalText).prop('disabled', false);
                });
        },

        resetProfileForm: function() {
            // Reload the page to reset form to original values
            window.location.reload();
        },

        initExpertiseManager: function() {
            // Add new expertise
            $('#jp-expertise-form').off('submit.jpmorgan').on('submit.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.addExpertise();
            });

            // Remove expertise
            $(document).off('click.jpmorgan', '.jp-expertise-remove').on('click.jpmorgan', '.jp-expertise-remove', function() {
                const expertiseId = $(this).data('id');
                JPMorganSettings.removeExpertise(expertiseId, $(this).closest('.jp-expertise-item'));
            });
        },

        addExpertise: function() {
            const form = $('#jp-expertise-form');
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            
            const title = $('#expertise_title').val().trim();
            const level = $('#expertise_level').val();
            const years = $('#expertise_years').val();

            if (!title) {
                JPMorganSettings.showNotification('Please enter an expertise area.', 'error');
                return;
            }

            submitButton.text('Adding...').prop('disabled', true);

            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_add_expertise',
                expertise_title: title,
                expertise_level: level,
                years_experience: years,
                nonce: sffc_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    JPMorganSettings.showNotification('Expertise added successfully!', 'success');
                    JPMorganSettings.addExpertiseToList(title, level, years);
                    form[0].reset();
                    JPMorganSettings.trackInteraction('expertise_added', { expertise: title });
                } else {
                    JPMorganSettings.showNotification('Failed to add expertise: ' + response.data, 'error');
                }
            })
            .fail(function() {
                JPMorganSettings.showNotification('Network error. Please try again.', 'error');
            })
            .always(function() {
                submitButton.text(originalText).prop('disabled', false);
            });
        },

        addExpertiseToList: function(title, level, years) {
            const list = $('#jp-expertise-list');
            const noExpertise = list.find('.jp-no-expertise');
            
            if (noExpertise.length) {
                noExpertise.remove();
            }

            const expertiseHtml = `
                <div class="jp-expertise-item" data-id="temp-${Date.now()}">
                    <div class="jp-expertise-content">
                        <h4>${this.escapeHtml(title)}</h4>
                        <p>Level: ${this.escapeHtml(level)} | Experience: ${this.escapeHtml(years)} years</p>
                    </div>
                    <button type="button" class="jp-expertise-remove" data-id="temp-${Date.now()}">×</button>
                </div>
            `;
            
            list.append(expertiseHtml);
        },

        removeExpertise: function(expertiseId, element) {
            if (confirm('Are you sure you want to remove this expertise area?')) {
                element.fadeOut(300, function() {
                    $(this).remove();
                    // If no expertise left, show message
                    if ($('#jp-expertise-list .jp-expertise-item').length === 0) {
                        $('#jp-expertise-list').append('<p class="jp-no-expertise">No expertise areas added yet.</p>');
                    }
                });
                
                // Track removal
                JPMorganSettings.trackInteraction('expertise_removed', { id: expertiseId });
            }
        },

        initProfilePictureUpload: function() {
            $('#jp-profile-upload').off('change.jpmorgan').on('change.jpmorgan', function(e) {
                const file = e.target.files[0];
                if (file) {
                    JPMorganSettings.uploadProfilePicture(file);
                }
            });

            $('#jp-remove-picture').off('click.jpmorgan').on('click.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.removeProfilePicture();
            });
        },

        uploadProfilePicture: function(file) {
            // Validate file
            if (!file.type.match('image.*')) {
                JPMorganSettings.showNotification('Please select a valid image file.', 'error');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                JPMorganSettings.showNotification('File size must be less than 5MB.', 'error');
                return;
            }

            // Show preview immediately
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#jp-profile-preview').html(`<img src="${e.target.result}" alt="Profile Picture" />`);
            };
            reader.readAsDataURL(file);

            // Upload file
            const formData = new FormData();
            formData.append('action', 'sffc_upload_profile_picture');
            formData.append('profile_picture', file);
            formData.append('nonce', sffc_ajax.nonce);

            $.ajax({
                url: sffc_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        JPMorganSettings.showNotification('Profile picture updated successfully!', 'success');
                        JPMorganSettings.trackInteraction('profile_picture_upload', { status: 'success' });
                    } else {
                        JPMorganSettings.showNotification('Upload failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    JPMorganSettings.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        removeProfilePicture: function() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                $('#jp-profile-preview').html(`
                    <div class="jp-profile-placeholder">
                        ${$('#jp-profile-preview').data('initial') || 'P'}
                    </div>
                `);
                
                // Update backend
                $.post(sffc_ajax.ajax_url, {
                    action: 'sffc_remove_profile_picture',
                    nonce: sffc_ajax.nonce
                });
                
                JPMorganSettings.showNotification('Profile picture removed.', 'success');
                JPMorganSettings.trackInteraction('profile_picture_removed');
            }
        },

        initCareerPreferences: function() {
            $('#jp-career-preferences-form').off('submit.jpmorgan').on('submit.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.saveCareerPreferences();
            });

            // Save opportunity
            $(document).off('click.jpmorgan', '.jp-save-opportunity').on('click.jpmorgan', '.jp-save-opportunity', function() {
                const opportunityId = $(this).data('id');
                JPMorganSettings.saveOpportunity(opportunityId, $(this));
            });
        },

        saveCareerPreferences: function() {
            const form = $('#jp-career-preferences-form');
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            
            submitButton.text('Saving...').prop('disabled', true);

            const formData = {
                action: 'sffc_save_career_preferences',
                nonce: sffc_ajax.nonce,
                preferred_locations: $('#preferred_locations').val(),
                salary_expectation: $('#salary_expectation').val(),
                job_alerts: $('#job_alerts').val()
            };

            $.post(sffc_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        JPMorganSettings.showNotification('Career preferences saved!', 'success');
                        JPMorganSettings.trackInteraction('career_preferences_saved', formData);
                    } else {
                        JPMorganSettings.showNotification('Failed to save preferences: ' + response.data, 'error');
                    }
                })
                .always(function() {
                    submitButton.text(originalText).prop('disabled', false);
                });
        },

        saveOpportunity: function(opportunityId, button) {
            const originalText = button.text();
            button.text('Saving...').prop('disabled', true);

            $.post(sffc_ajax.ajax_url, {
                action: 'sffc_save_opportunity',
                opportunity_id: opportunityId,
                nonce: sffc_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    button.text('Saved').addClass('jp-btn-success');
                    JPMorganSettings.showNotification('Opportunity saved!', 'success');
                    JPMorganSettings.trackInteraction('opportunity_saved', { id: opportunityId });
                } else {
                    button.text(originalText).prop('disabled', false);
                    JPMorganSettings.showNotification('Failed to save opportunity', 'error');
                }
            })
            .fail(function() {
                button.text(originalText).prop('disabled', false);
                JPMorganSettings.showNotification('Network error. Please try again.', 'error');
            });
        },

        initPreferencesForm: function() {
            $('#jp-preferences-form').off('submit.jpmorgan').on('submit.jpmorgan', function(e) {
                e.preventDefault();
                JPMorganSettings.savePreferences();
            });
        },

        savePreferences: function() {
            const form = $('#jp-preferences-form');
            const submitButton = form.find('button[type="submit"]');
            const originalText = submitButton.text();
            
            submitButton.text('Saving...').prop('disabled', true);

            const formData = {
                action: 'sffc_save_user_preferences',
                nonce: sffc_ajax.nonce
            };

            // Collect form data
            form.find('input, select').each(function() {
                const field = $(this);
                if (field.attr('type') === 'checkbox') {
                    formData[field.attr('name')] = field.is(':checked') ? 1 : 0;
                } else {
                    formData[field.attr('name')] = field.val();
                }
            });

            $.post(sffc_ajax.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        JPMorganSettings.showNotification('Preferences saved successfully!', 'success');
                        JPMorganSettings.trackInteraction('preferences_saved', formData);
                    } else {
                        JPMorganSettings.showNotification('Failed to save preferences: ' + response.data, 'error');
                    }
                })
                .always(function() {
                    submitButton.text(originalText).prop('disabled', false);
                });
        },

        showNotification: function(message, type = 'info') {
            // Remove existing notifications
            $('.jp-notification').remove();
            
            const notification = $(`
                <div class="jp-notification jp-notification-${type}">
                    <div class="jp-notification-content">
                        <span class="jp-notification-icon">
                            ${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}
                        </span>
                        <span class="jp-notification-message">${this.escapeHtml(message)}</span>
                        <button type="button" class="jp-notification-close">×</button>
                    </div>
                </div>
            `);
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.addClass('jp-notification-show');
            }, 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideNotification(notification);
            }, 5000);
            
            // Close button
            notification.find('.jp-notification-close').on('click', () => {
                this.hideNotification(notification);
            });
        },

        hideNotification: function(notification) {
            notification.removeClass('jp-notification-show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        },

        trackInteraction: function(action, details = {}) {
            if (typeof sffc_ajax !== 'undefined') {
                $.post(sffc_ajax.ajax_url, {
                    action: 'sffc_track_profile_interaction',
                    interaction_action: action,
                    details: details,
                    nonce: sffc_ajax.nonce
                });
            }
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        bindEvents: function() {
            // Handle form auto-save drafts
            let saveTimeout;
            $('.jp-form-input, .jp-form-textarea, .jp-form-select').on('input change', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    JPMorganSettings.saveDraft();
                }, 2000);
            });

            // Handle keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.which) {
                        case 83: // Ctrl+S / Cmd+S
                            e.preventDefault();
                            const activeSection = $('.jp-settings-section.active');
                            const submitButton = activeSection.find('button[type="submit"]');
                            if (submitButton.length) {
                                submitButton.click();
                            }
                            break;
                    }
                }
            });
        },

        saveDraft: function() {
            // Auto-save form drafts to localStorage for recovery
            const activeSection = $('.jp-settings-section.active').attr('id');
            const formData = {};
            
            $(`.jp-settings-section.active input, .jp-settings-section.active textarea, .jp-settings-section.active select`).each(function() {
                const field = $(this);
                if (field.attr('type') === 'checkbox') {
                    formData[field.attr('name')] = field.is(':checked');
                } else {
                    formData[field.attr('name')] = field.val();
                }
            });
            
            localStorage.setItem(`jp_settings_draft_${activeSection}`, JSON.stringify(formData));
        },

        loadDraft: function() {
            // Load saved drafts from localStorage
            const activeSection = $('.jp-settings-section.active').attr('id');
            const saved = localStorage.getItem(`jp_settings_draft_${activeSection}`);
            
            if (saved) {
                try {
                    const formData = JSON.parse(saved);
                    Object.keys(formData).forEach(name => {
                        const field = $(`.jp-settings-section.active [name="${name}"]`);
                        if (field.attr('type') === 'checkbox') {
                            field.prop('checked', formData[name]);
                        } else {
                            field.val(formData[name]);
                        }
                    });
                } catch (e) {
                    console.warn('Failed to load draft:', e);
                }
            }
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.jp-settings-container').length) {
            JPMorganSettings.init();
        }
    });

    // Make globally available
    window.JPMorganSettings = JPMorganSettings;

})(jQuery);

// Add notification styles
const settingsStyles = `
    <style>
    .jp-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        background: var(--jp-white);
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(139, 69, 19, 0.15);
        border-left: 4px solid var(--jp-brown);
        transform: translateX(100%);
        transition: transform 0.3s ease;
        min-width: 320px;
        max-width: 400px;
    }
    
    .jp-notification-show {
        transform: translateX(0);
    }
    
    .jp-notification-success {
        border-left-color: #22c55e;
    }
    
    .jp-notification-error {
        border-left-color: var(--jp-maroon);
    }
    
    .jp-notification-content {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        gap: 0.75rem;
    }
    
    .jp-notification-icon {
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .jp-notification-success .jp-notification-icon {
        color: #22c55e;
    }
    
    .jp-notification-error .jp-notification-icon {
        color: var(--jp-maroon);
    }
    
    .jp-notification-message {
        flex: 1;
        color: var(--jp-gray-700);
    }
    
    .jp-notification-close {
        background: none;
        border: none;
        font-size: 1.2rem;
        color: var(--jp-gray-500);
        cursor: pointer;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .jp-notification-close:hover {
        color: var(--jp-gray-700);
    }
    
    .jp-btn-success {
        background: #22c55e !important;
        color: white !important;
    }
    </style>
`;

jQuery('head').append(settingsStyles);