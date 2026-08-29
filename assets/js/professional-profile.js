jQuery(document).ready(function($) {
    'use strict';

    // Professional Profile Manager
    const ProfessionalProfile = {
        init: function() {
            this.bindEvents();
            this.loadProfileData();
        },

        bindEvents: function() {
            // Modal triggers
            $('#edit-profile-btn').on('click', this.openProfileEditModal);
            $('#add-expertise-btn').on('click', this.openExpertiseModal);
            $('.sffc-modal-close').on('click', this.closeModal);
            
            // Form submissions
            $('#sffc-profile-edit-form').on('submit', this.handleProfileUpdate);
            $('#sffc-expertise-add-form').on('submit', this.handleExpertiseAdd);
            
            // Quick actions
            $('#view-dashboard').on('click', this.showDashboard);
            $('#networking-btn, #expand-network-btn').on('click', this.showNetworking);
            
            // Click outside modal to close
            $('.sffc-modal').on('click', function(e) {
                if (e.target === this) {
                    ProfessionalProfile.closeModal();
                }
            });

            // Escape key to close modal
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    ProfessionalProfile.closeModal();
                }
            });
        },

        openProfileEditModal: function(e) {
            e.preventDefault();
            $('#sffc-profile-edit-modal').fadeIn(300);
            $('body').addClass('sffc-modal-open');
        },

        openExpertiseModal: function(e) {
            e.preventDefault();
            $('#sffc-expertise-add-modal').fadeIn(300);
            $('body').addClass('sffc-modal-open');
        },

        closeModal: function() {
            $('.sffc-modal').fadeOut(300);
            $('body').removeClass('sffc-modal-open');
            $('.sffc-form-group input, .sffc-form-group textarea').removeClass('error');
        },

        handleProfileUpdate: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.text('Saving...').prop('disabled', true);
            
            const formData = new FormData(this);
            formData.append('action', 'sffc_update_professional_profile');
            
            $.ajax({
                url: sffc_ajax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        ProfessionalProfile.showNotification('Profile updated successfully!', 'success');
                        ProfessionalProfile.closeModal();
                        ProfessionalProfile.refreshProfileDisplay();
                    } else {
                        ProfessionalProfile.showNotification(response.data || 'Failed to update profile', 'error');
                    }
                },
                error: function() {
                    ProfessionalProfile.showNotification('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },

        handleExpertiseAdd: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            
            // Validation
            const expertiseTitle = $form.find('#expertise_title').val().trim();
            if (!expertiseTitle) {
                ProfessionalProfile.showFieldError('#expertise_title', 'Expertise area is required');
                return;
            }
            
            $submitBtn.text('Adding...').prop('disabled', true);
            
            const formData = new FormData(this);
            formData.append('action', 'sffc_add_expertise');
            
            $.ajax({
                url: sffc_ajax.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        ProfessionalProfile.showNotification('Expertise added successfully!', 'success');
                        ProfessionalProfile.closeModal();
                        $form[0].reset();
                        ProfessionalProfile.refreshExpertiseDisplay();
                    } else {
                        ProfessionalProfile.showNotification(response.data || 'Failed to add expertise', 'error');
                    }
                },
                error: function() {
                    ProfessionalProfile.showNotification('Connection error. Please try again.', 'error');
                },
                complete: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                }
            });
        },

        loadProfileData: function() {
            $.ajax({
                url: sffc_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sffc_get_profile_data',
                    nonce: sffc_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data) {
                        ProfessionalProfile.updateAnalyticsDisplay(response.data.analytics);
                        ProfessionalProfile.updateCompletionScore(response.data.completion_score);
                    }
                },
                error: function() {
                    console.log('Failed to load profile data');
                }
            });
        },

        refreshProfileDisplay: function() {
            // Reload the page to show updated profile info
            // In a more sophisticated implementation, you'd update specific elements
            window.location.reload();
        },

        refreshExpertiseDisplay: function() {
            // Reload the page to show new expertise
            // In a more sophisticated implementation, you'd append the new expertise badge
            window.location.reload();
        },

        updateAnalyticsDisplay: function(analytics) {
            if (analytics) {
                $('.sffc-analytics-metric').each(function() {
                    const metric = $(this).find('.sffc-metric-label').text().toLowerCase();
                    if (metric.includes('senna')) {
                        $(this).find('.sffc-metric-value').text(analytics.senna_interactions || 0);
                    } else if (metric.includes('profile')) {
                        $(this).find('.sffc-metric-value').text(analytics.profile_views || 0);
                    } else if (metric.includes('introduction')) {
                        $(this).find('.sffc-metric-value').text(analytics.introduction_requests || 0);
                    } else if (metric.includes('networking')) {
                        $(this).find('.sffc-metric-value').text(analytics.networking_activity || 0);
                    }
                });
            }
        },

        updateCompletionScore: function(score) {
            $('.sffc-completion-circle span').text(score + '%');
            
            if (score >= 80) {
                $('.sffc-completion-banner').fadeOut(500);
            }
        },

        showDashboard: function(e) {
            e.preventDefault();
            // Navigate to full dashboard view
            window.location.href = window.location.href.split('#')[0] + '#dashboard';
            ProfessionalProfile.showNotification('Loading your professional dashboard...', 'info');
        },

        showNetworking: function(e) {
            e.preventDefault();
            // Navigate to networking section
            window.location.href = window.location.href.split('#')[0] + '#networking';
            ProfessionalProfile.showNotification('Opening networking opportunities...', 'info');
        },

        showNotification: function(message, type) {
            type = type || 'info';
            
            // Remove existing notifications
            $('.sffc-notification').remove();
            
            const $notification = $('<div class="sffc-notification sffc-notification--' + type + '">')
                .html('<span>' + message + '</span><button class="sffc-notification-close">&times;</button>')
                .appendTo('body');
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.find('.sffc-notification-close').on('click', function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Show notification
            $notification.hide().slideDown(300);
        },

        showFieldError: function(fieldSelector, message) {
            $(fieldSelector).addClass('error');
            
            // Remove existing error message
            $(fieldSelector).next('.sffc-field-error').remove();
            
            // Add error message
            $('<div class="sffc-field-error">' + message + '</div>')
                .insertAfter(fieldSelector)
                .hide()
                .slideDown(200);
            
            // Focus on field
            $(fieldSelector).focus();
            
            // Remove error on input
            $(fieldSelector).one('input', function() {
                $(this).removeClass('error');
                $(this).next('.sffc-field-error').slideUp(200, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize professional profile functionality
    ProfessionalProfile.init();

    // Track profile interactions for analytics
    function trackProfileInteraction(action, details) {
        $.ajax({
            url: sffc_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'sffc_track_profile_interaction',
                interaction_action: action,
                details: details,
                nonce: sffc_ajax.nonce
            }
        });
    }

    // Track various profile interactions
    $('#edit-profile-btn').on('click', function() {
        trackProfileInteraction('profile_edit_opened', {});
    });

    $('#add-expertise-btn').on('click', function() {
        trackProfileInteraction('expertise_modal_opened', {});
    });

    $('.sffc-quick-action').on('click', function() {
        const actionType = $(this).attr('id') || 'unknown_action';
        trackProfileInteraction('quick_action_clicked', { action_type: actionType });
    });

    // Professional profile enhancement features
    const ProfileEnhancements = {
        init: function() {
            this.addHoverEffects();
            this.addKeyboardNavigation();
            this.addAccessibilityFeatures();
        },

        addHoverEffects: function() {
            // Enhanced card hover effects
            $('.sffc-professional-card').on('mouseenter', function() {
                $(this).addClass('sffc-card-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('sffc-card-hover');
            });

            // Expertise badge interactions
            $('.sffc-expertise-badge').on('mouseenter', function() {
                $(this).addClass('sffc-expertise-hover');
            }).on('mouseleave', function() {
                $(this).removeClass('sffc-expertise-hover');
            });
        },

        addKeyboardNavigation: function() {
            // Tab navigation enhancement
            $('.sffc-action-button, .sffc-quick-action').on('keydown', function(e) {
                if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
                    e.preventDefault();
                    $(this).click();
                }
            });
        },

        addAccessibilityFeatures: function() {
            // Add ARIA labels for screen readers
            $('.sffc-completion-circle').attr('aria-label', 'Profile completion percentage');
            $('.sffc-analytics-metric').each(function() {
                const label = $(this).find('.sffc-metric-label').text();
                const value = $(this).find('.sffc-metric-value').text();
                $(this).attr('aria-label', label + ': ' + value);
            });
        }
    };

    ProfileEnhancements.init();
});

// CSS for notifications (to be added to the CSS file)
const notificationStyles = `
    .sffc-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--sffc-professional-card);
        border: 1px solid var(--sffc-professional-border);
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: var(--sffc-professional-shadow-elevated);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        max-width: 400px;
        font-family: var(--sffc-font-body);
    }
    
    .sffc-notification--success {
        border-left: 4px solid var(--sffc-professional-mint);
    }
    
    .sffc-notification--error {
        border-left: 4px solid #ff6b6b;
    }
    
    .sffc-notification--info {
        border-left: 4px solid var(--sffc-professional-ink);
    }
    
    .sffc-notification-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: var(--sffc-professional-text-muted);
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .sffc-field-error {
        color: #ff6b6b;
        font-size: 12px;
        margin-top: 4px;
        padding: 4px 8px;
        background: rgba(255, 107, 107, 0.1);
        border-radius: 4px;
    }
    
    .sffc-form-group input.error,
    .sffc-form-group textarea.error {
        border-color: #ff6b6b;
        box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
    }
    
    body.sffc-modal-open {
        overflow: hidden;
    }
    
    .sffc-card-hover {
        transform: translateY(-2px);
        box-shadow: var(--sffc-professional-shadow-elevated);
    }
    
    .sffc-expertise-hover {
        background: var(--sffc-professional-card);
        border-color: var(--sffc-professional-mint);
    }
`;

// Inject notification styles
if (!document.getElementById('sffc-notification-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'sffc-notification-styles';
    styleSheet.textContent = notificationStyles;
    document.head.appendChild(styleSheet);
}