/**
 * Career Onboarding JavaScript
 *
 * Handles step navigation, form validation, and data persistence
 * for the McKinsey-style onboarding experience.
 *
 * @package SFFC_Careers
 */

(function($) {
    'use strict';

    const Onboarding = {
        currentStep: 1,
        totalSteps: 7,
        isSubmitting: false,
        skills: [],

        /**
         * Initialize onboarding
         */
        init: function() {
            if (!$('#sffc-onboarding').length) {
                return;
            }

            // Add active class to body
            $('body').addClass('sffc-onboarding-active');

            // Get current step from data attribute
            this.currentStep = parseInt($('#sffc-onboarding').data('step')) || 1;
            this.totalSteps = sffcOnboarding.total_steps || 7;

            // Initialize skills array from existing tags
            this.initSkills();

            // Bind events
            this.bindEvents();

            // Show current step
            this.showStep(this.currentStep);
        },

        /**
         * Initialize skills from existing tags
         */
        initSkills: function() {
            const $hidden = $('#sffc-skills-hidden');
            if ($hidden.length && $hidden.val()) {
                try {
                    this.skills = JSON.parse($hidden.val());
                } catch (e) {
                    this.skills = [];
                }
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Navigation buttons
            $('#sffc-onboarding-next').on('click', () => this.nextStep());
            $('#sffc-onboarding-prev').on('click', () => this.prevStep());
            $('#sffc-onboarding-skip').on('click', () => this.skipOnboarding());
            $('#sffc-go-to-dashboard').on('click', () => this.completeOnboarding());

            // Skills functionality
            $('#sffc-add-skill-btn').on('click', () => this.addSkillFromInput());
            $('#sffc-skill-input').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.addSkillFromInput();
                }
            });

            // Suggested skills
            $(document).on('click', '.sffc-onboarding-suggested-skill', function() {
                const skill = $(this).data('skill');
                self.addSkill(skill);
                $(this).addClass('added');
            });

            // Remove skill
            $(document).on('click', '.sffc-remove-skill', function() {
                const $tag = $(this).closest('.sffc-onboarding-skill-tag');
                const skill = $tag.data('skill');
                self.removeSkill(skill);
                $tag.remove();

                // Show suggested skill button again
                $(`.sffc-onboarding-suggested-skill[data-skill="${skill}"]`).removeClass('added');
            });

            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Allow escape to skip
                }
            });

            // Auto-save on field change
            $(document).on('change', '.sffc-onboarding-field input, .sffc-onboarding-field select', function() {
                // Optional: auto-save field changes
            });
        },

        /**
         * Show specific step
         */
        showStep: function(step) {
            // Hide all steps
            $('.sffc-onboarding-step').removeClass('active');

            // Show target step
            $(`#sffc-step-${step}`).addClass('active');

            // Update progress
            this.updateProgress(step);

            // Update navigation buttons
            this.updateNavigation(step);

            // Update current step display
            $('#sffc-current-step').text(step);

            // Scroll to top of step
            $('.sffc-onboarding-steps').scrollTop(0);

            // Focus first input if form step
            setTimeout(() => {
                $(`#sffc-step-${step} input:visible:first`).focus();
            }, 300);
        },

        /**
         * Update progress bar
         */
        updateProgress: function(step) {
            const percentage = (step / this.totalSteps) * 100;
            $('.sffc-onboarding-progress-fill').css('width', percentage + '%');
        },

        /**
         * Update navigation buttons
         */
        updateNavigation: function(step) {
            // Previous button visibility
            if (step <= 1) {
                $('#sffc-onboarding-prev').css('visibility', 'hidden');
            } else {
                $('#sffc-onboarding-prev').css('visibility', 'visible');
            }

            // Next button text
            if (step === this.totalSteps) {
                $('#sffc-onboarding-next').hide();
                $('#sffc-onboarding-skip').hide();
            } else if (step === this.totalSteps - 1) {
                $('#sffc-onboarding-next').html('Complete <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>');
            } else {
                $('#sffc-onboarding-next').html('Continue <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>');
            }
        },

        /**
         * Go to next step
         */
        nextStep: function() {
            if (this.isSubmitting) return;

            // Validate current step
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            // Save current step data
            this.saveStep(this.currentStep).then(() => {
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                    this.showStep(this.currentStep);
                } else {
                    this.completeOnboarding();
                }
            });
        },

        /**
         * Go to previous step
         */
        prevStep: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
            }
        },

        /**
         * Validate current step
         */
        validateStep: function(step) {
            const $step = $(`#sffc-step-${step}`);
            let isValid = true;

            // Check required fields
            $step.find('input[required], select[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('sffc-field-error');
                    isValid = false;
                } else {
                    $(this).removeClass('sffc-field-error');
                }
            });

            // Step-specific validation
            switch (step) {
                case 2: // Identity
                    // Name is required
                    const name = $step.find('[name="full_name"]').val();
                    if (!name || name.trim().length < 2) {
                        $step.find('[name="full_name"]').addClass('sffc-field-error');
                        isValid = false;
                    }
                    break;

                case 5: // Skills
                    // At least 3 skills recommended but not required
                    if (this.skills.length < 1) {
                        // Show a gentle reminder but don't block
                        this.showToast('Adding skills helps us find better matches for you', 'info');
                    }
                    break;
            }

            return isValid;
        },

        /**
         * Save current step data
         */
        saveStep: function(step) {
            const self = this;
            const $step = $(`#sffc-step-${step}`);
            const data = {};

            // Collect form data
            $step.find('input, select').each(function() {
                const $input = $(this);
                const name = $input.attr('name');

                if (!name) return;

                if ($input.attr('type') === 'checkbox') {
                    if (!data[name.replace('[]', '')]) {
                        data[name.replace('[]', '')] = [];
                    }
                    if ($input.is(':checked')) {
                        data[name.replace('[]', '')].push($input.val());
                    }
                } else if ($input.attr('type') === 'radio') {
                    if ($input.is(':checked')) {
                        data[name] = $input.val();
                    }
                } else {
                    data[name] = $input.val();
                }
            });

            // Add skills if on skills step
            if (step === 5) {
                data.skills = this.skills;
            }

            return new Promise((resolve, reject) => {
                self.isSubmitting = true;

                $.ajax({
                    url: sffcOnboarding.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_save_onboarding_step',
                        nonce: sffcOnboarding.nonce,
                        step: step,
                        data: data
                    },
                    success: function(response) {
                        self.isSubmitting = false;
                        if (response.success) {
                            // Also save to localStorage for quick access
                            self.saveToLocalStorage(data);
                            resolve(response.data);
                        } else {
                            self.showToast(response.data?.message || 'Failed to save', 'error');
                            reject(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        self.isSubmitting = false;
                        self.showToast('Connection error. Please try again.', 'error');
                        reject(error);
                    }
                });
            });
        },

        /**
         * Save to localStorage
         */
        saveToLocalStorage: function(data) {
            try {
                let profile = JSON.parse(localStorage.getItem('sffc_user_profile') || '{}');
                profile = { ...profile, ...data };
                localStorage.setItem('sffc_user_profile', JSON.stringify(profile));
            } catch (e) {
                console.error('Error saving to localStorage:', e);
            }
        },

        /**
         * Complete onboarding
         */
        completeOnboarding: function() {
            const self = this;

            if (this.isSubmitting) return;
            this.isSubmitting = true;

            // Show loading state
            $('#sffc-go-to-dashboard').prop('disabled', true).text('Setting up your dashboard...');

            $.ajax({
                url: sffcOnboarding.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_complete_onboarding',
                    nonce: sffcOnboarding.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to dashboard
                        window.location.href = response.data.redirect_url || sffcOnboarding.dashboard_url;
                    } else {
                        self.isSubmitting = false;
                        $('#sffc-go-to-dashboard').prop('disabled', false).html('Go to Dashboard <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>');
                        self.showToast(response.data?.message || 'Failed to complete', 'error');
                    }
                },
                error: function() {
                    self.isSubmitting = false;
                    $('#sffc-go-to-dashboard').prop('disabled', false).html('Go to Dashboard <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>');
                    self.showToast('Connection error. Please try again.', 'error');
                }
            });
        },

        /**
         * Skip onboarding
         */
        skipOnboarding: function() {
            if (confirm('Are you sure you want to skip? You can complete your profile later from the dashboard.')) {
                $.ajax({
                    url: sffcOnboarding.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'sffc_skip_onboarding',
                        nonce: sffcOnboarding.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            window.location.href = response.data.redirect_url || sffcOnboarding.dashboard_url;
                        }
                    }
                });
            }
        },

        /**
         * Add skill from input
         */
        addSkillFromInput: function() {
            const $input = $('#sffc-skill-input');
            const skill = $input.val().trim();

            if (skill) {
                this.addSkill(skill);
                $input.val('').focus();
            }
        },

        /**
         * Add skill
         */
        addSkill: function(skill) {
            // Check if already exists
            if (this.skills.includes(skill)) {
                this.showToast('Skill already added', 'info');
                return;
            }

            // Add to array
            this.skills.push(skill);

            // Update hidden field
            $('#sffc-skills-hidden').val(JSON.stringify(this.skills));

            // Add tag to UI
            const $tag = $(`
                <span class="sffc-onboarding-skill-tag" data-skill="${this.escapeHtml(skill)}">
                    ${this.escapeHtml(skill)}
                    <button type="button" class="sffc-remove-skill">&times;</button>
                </span>
            `);

            $('#sffc-selected-skills').append($tag);
        },

        /**
         * Remove skill
         */
        removeSkill: function(skill) {
            const index = this.skills.indexOf(skill);
            if (index > -1) {
                this.skills.splice(index, 1);
                $('#sffc-skills-hidden').val(JSON.stringify(this.skills));
            }
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Remove existing toasts
            $('.sffc-onboarding-toast').remove();

            const icons = {
                success: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                error: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
                info: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
            };

            const $toast = $(`
                <div class="sffc-onboarding-toast sffc-toast-${type}">
                    ${icons[type] || icons.info}
                    <span>${this.escapeHtml(message)}</span>
                </div>
            `);

            // Add toast styles if not present
            if (!$('#sffc-toast-styles').length) {
                $('head').append(`
                    <style id="sffc-toast-styles">
                        .sffc-onboarding-toast {
                            position: fixed;
                            bottom: 100px;
                            left: 50%;
                            transform: translateX(-50%);
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            padding: 14px 20px;
                            background: #1e293b;
                            color: #ffffff;
                            font-size: 14px;
                            font-weight: 500;
                            border-radius: 8px;
                            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                            z-index: 1000000;
                            animation: toastIn 0.3s ease;
                        }
                        .sffc-toast-error { background: #dc2626; }
                        .sffc-toast-success { background: #22c55e; }
                        @keyframes toastIn {
                            from { opacity: 0; transform: translate(-50%, 20px); }
                            to { opacity: 1; transform: translate(-50%, 0); }
                        }
                    </style>
                `);
            }

            $('body').append($toast);

            // Auto-remove after 4 seconds
            setTimeout(() => {
                $toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        Onboarding.init();
    });

})(jQuery);
