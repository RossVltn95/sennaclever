/**
 * Profile Settings Component
 * Allows users to view/edit their career profile and adjust match threshold
 */

(function($) {
    'use strict';

    // Field configurations
    const PROFILE_FIELDS = {
        full_name: { label: 'Full Name', type: 'text', required: true },
        current_role: { label: 'Current Role', type: 'text', required: false },
        years_experience: {
            label: 'Years of Experience',
            type: 'select',
            options: [
                { value: '0-2', label: 'Early Career (0-2 years)' },
                { value: '3-5', label: 'Developing (3-5 years)' },
                { value: '6-10', label: 'Experienced (6-10 years)' },
                { value: '11-15', label: 'Senior (11-15 years)' },
                { value: '16+', label: 'Executive (16+ years)' }
            ]
        },
        target_seniority: {
            label: 'Target Seniority',
            type: 'select',
            options: [
                { value: 'intern', label: 'Intern/Trainee' },
                { value: 'analyst', label: 'Analyst' },
                { value: 'associate', label: 'Associate' },
                { value: 'senior_associate', label: 'Senior Associate' },
                { value: 'vp', label: 'Vice President' },
                { value: 'director', label: 'Director' },
                { value: 'md', label: 'Managing Director' },
                { value: 'partner', label: 'Partner/Principal' }
            ]
        },
        education_level: {
            label: 'Education Level',
            type: 'select',
            options: [
                { value: 'high_school', label: 'High School' },
                { value: 'bachelors', label: "Bachelor's Degree" },
                { value: 'masters', label: "Master's Degree" },
                { value: 'mba', label: 'MBA' },
                { value: 'phd', label: 'PhD/Doctorate' }
            ]
        },
        work_preference: {
            label: 'Work Style',
            type: 'select',
            options: [
                { value: 'office', label: 'In Office' },
                { value: 'hybrid', label: 'Hybrid' },
                { value: 'remote', label: 'Fully Remote' },
                { value: 'flexible', label: 'Flexible' }
            ]
        },
        availability: {
            label: 'Availability',
            type: 'select',
            options: [
                { value: 'immediate', label: 'Immediately available' },
                { value: '2_weeks', label: '2 weeks notice' },
                { value: '1_month', label: '1 month notice' },
                { value: '2_months', label: '2 months notice' },
                { value: '3_months', label: '3+ months notice' }
            ]
        }
    };

    /**
     * ProfileSettings Class
     */
    class ProfileSettings {
        constructor() {
            this.profile = {};
            this.isOpen = false;
            this.init();
        }

        init() {
            this.loadProfile();
            this.bindGlobalEvents();
        }

        loadProfile() {
            try {
                const stored = localStorage.getItem('sffc_user_profile');
                if (stored) {
                    this.profile = JSON.parse(stored);
                }
            } catch (e) {
                console.error('Error loading profile:', e);
                this.profile = {};
            }
        }

        saveProfile() {
            try {
                localStorage.setItem('sffc_user_profile', JSON.stringify(this.profile));

                // Trigger event for other components
                $(document).trigger('sffc:profile:updated', [this.profile]);

                // If logged in, save to server
                if (window.sffc_profile?.is_logged_in || window.sffcMatchData?.is_logged_in) {
                    this.saveToServer();
                }
            } catch (e) {
                console.error('Error saving profile:', e);
            }
        }

        saveToServer() {
            const ajaxUrl = window.sffc_profile?.ajax_url || window.sffc_ajax?.ajax_url || '/wp-admin/admin-ajax.php';
            const nonce = window.sffc_profile?.nonce || window.sffc_ajax?.nonce || '';

            // Save each field
            Object.keys(this.profile).forEach(field => {
                if (this.profile[field] !== undefined && this.profile[field] !== null) {
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'sffc_save_profile_answer',
                            field: field,
                            value: this.profile[field],
                            nonce: nonce
                        }
                    });
                }
            });
        }

        bindGlobalEvents() {
            // Open settings from various triggers
            $(document).on('click', '.sffc-open-profile-settings, #open-profile-settings', () => {
                this.open();
            });

            // Escape key to close
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });
        }

        open() {
            if (this.isOpen) return;

            this.loadProfile(); // Refresh data
            this.render();
            this.isOpen = true;

            // Prevent body scroll
            $('body').addClass('sffc-modal-open');
        }

        close() {
            const $modal = $('#sffc-profile-settings-overlay');
            $modal.removeClass('active');

            setTimeout(() => {
                $modal.remove();
                $('body').removeClass('sffc-modal-open');
            }, 300);

            this.isOpen = false;
        }

        render() {
            // Remove existing modal
            $('#sffc-profile-settings-overlay').remove();

            const completion = this.calculateCompletion();
            const threshold = this.profile.match_threshold || 60;

            const modalHTML = `
                <div class="sffc-profile-settings-overlay" id="sffc-profile-settings-overlay">
                    <div class="sffc-profile-settings-modal">
                        <div class="sffc-ps-header">
                            <div class="sffc-ps-title-section">
                                <h2 class="sffc-ps-title">Career Profile</h2>
                                <div class="sffc-ps-completion">
                                    <div class="sffc-ps-completion-bar">
                                        <div class="sffc-ps-completion-fill" style="width: ${completion}%"></div>
                                    </div>
                                    <span class="sffc-ps-completion-text">${completion}% complete</span>
                                </div>
                            </div>
                            <button class="sffc-ps-close" id="ps-close">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>

                        <div class="sffc-ps-content">
                            <!-- Match Threshold Section -->
                            <div class="sffc-ps-section sffc-ps-threshold-section">
                                <h3 class="sffc-ps-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <path d="M12 6v6l4 2"/>
                                    </svg>
                                    Match Threshold
                                </h3>
                                <p class="sffc-ps-section-desc">Only highlight jobs that match at least this percentage</p>
                                <div class="sffc-ps-threshold-control">
                                    <input type="range"
                                           class="sffc-ps-threshold-slider"
                                           id="ps-threshold-slider"
                                           min="40" max="90" step="5"
                                           value="${threshold}">
                                    <div class="sffc-ps-threshold-labels">
                                        <span>40%</span>
                                        <span class="sffc-ps-threshold-value" id="ps-threshold-value">${threshold}%</span>
                                        <span>90%</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Basic Info Section -->
                            <div class="sffc-ps-section">
                                <h3 class="sffc-ps-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    Basic Information
                                </h3>
                                <div class="sffc-ps-fields">
                                    ${this.renderField('full_name')}
                                    ${this.renderField('current_role')}
                                    ${this.renderField('years_experience')}
                                    ${this.renderField('target_seniority')}
                                </div>
                            </div>

                            <!-- Preferences Section -->
                            <div class="sffc-ps-section">
                                <h3 class="sffc-ps-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                                    </svg>
                                    Preferences
                                </h3>
                                <div class="sffc-ps-fields">
                                    ${this.renderField('education_level')}
                                    ${this.renderField('work_preference')}
                                    ${this.renderField('availability')}
                                </div>
                            </div>

                            <!-- Skills Section -->
                            <div class="sffc-ps-section">
                                <h3 class="sffc-ps-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                        <polyline points="22 4 12 14.01 9 11.01"/>
                                    </svg>
                                    Skills (${(this.profile.skills || []).length})
                                </h3>
                                <div class="sffc-ps-skills-display">
                                    ${this.renderSkills()}
                                </div>
                                <button class="sffc-ps-edit-skills-btn" id="ps-edit-skills">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg>
                                    Edit Skills
                                </button>
                            </div>
                        </div>

                        <div class="sffc-ps-footer">
                            <button class="sffc-ps-btn-secondary" id="ps-restart-profile">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 4v6h6"/>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                </svg>
                                Restart Profile
                            </button>
                            <button class="sffc-ps-btn-primary" id="ps-save-close">
                                Save & Close
                            </button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHTML);

            // Animate in
            setTimeout(() => {
                $('#sffc-profile-settings-overlay').addClass('active');
            }, 10);

            this.bindModalEvents();
        }

        renderField(fieldKey) {
            const config = PROFILE_FIELDS[fieldKey];
            if (!config) return '';

            const value = this.profile[fieldKey] || '';
            const requiredMark = config.required ? '<span class="required">*</span>' : '';

            if (config.type === 'select') {
                const options = config.options.map(opt =>
                    `<option value="${opt.value}" ${value === opt.value ? 'selected' : ''}>${opt.label}</option>`
                ).join('');

                return `
                    <div class="sffc-ps-field">
                        <label class="sffc-ps-label">${config.label}${requiredMark}</label>
                        <select class="sffc-ps-select" data-field="${fieldKey}">
                            <option value="">Select...</option>
                            ${options}
                        </select>
                    </div>
                `;
            }

            return `
                <div class="sffc-ps-field">
                    <label class="sffc-ps-label">${config.label}${requiredMark}</label>
                    <input type="text"
                           class="sffc-ps-input"
                           data-field="${fieldKey}"
                           value="${this.escapeHtml(value)}"
                           placeholder="Enter ${config.label.toLowerCase()}">
                </div>
            `;
        }

        renderSkills() {
            const skills = this.profile.skills || [];

            if (skills.length === 0) {
                return '<p class="sffc-ps-no-skills">No skills added yet</p>';
            }

            return skills.map(skill =>
                `<span class="sffc-ps-skill-tag">${this.escapeHtml(skill)}</span>`
            ).join('');
        }

        calculateCompletion() {
            const coreFields = ['full_name', 'years_experience', 'current_role', 'skills'];
            const optionalFields = ['target_seniority', 'education_level', 'work_preference', 'availability', 'match_threshold'];

            let coreCompleted = 0;
            coreFields.forEach(field => {
                const val = this.profile[field];
                if (val && (Array.isArray(val) ? val.length > 0 : val !== '')) {
                    coreCompleted++;
                }
            });

            let optionalCompleted = 0;
            optionalFields.forEach(field => {
                const val = this.profile[field];
                if (val && val !== '') {
                    optionalCompleted++;
                }
            });

            const corePercentage = (coreCompleted / coreFields.length) * 70;
            const optionalPercentage = (optionalCompleted / optionalFields.length) * 30;

            return Math.round(corePercentage + optionalPercentage);
        }

        bindModalEvents() {
            const self = this;

            // Close button
            $('#ps-close').on('click', () => this.close());

            // Click outside to close
            $('#sffc-profile-settings-overlay').on('click', function(e) {
                if (e.target === this) {
                    self.close();
                }
            });

            // Threshold slider
            $('#ps-threshold-slider').on('input', function() {
                const val = $(this).val();
                $('#ps-threshold-value').text(val + '%');
                self.profile.match_threshold = parseInt(val);

                // Update slider fill
                const percentage = ((val - 40) / 50) * 100;
                $(this).css('background', `linear-gradient(to right, #3B82F6 0%, #3B82F6 ${percentage}%, #E5E7EB ${percentage}%, #E5E7EB 100%)`);
            }).trigger('input');

            // Field changes
            $('.sffc-ps-input, .sffc-ps-select').on('change input', function() {
                const field = $(this).data('field');
                const value = $(this).val();
                self.profile[field] = value;
            });

            // Edit skills - opens profile builder
            $('#ps-edit-skills').on('click', () => {
                this.close();
                if (typeof window.openProfileBuilder === 'function') {
                    window.openProfileBuilder();
                }
            });

            // Restart profile
            $('#ps-restart-profile').on('click', () => {
                if (confirm('This will clear your profile and start fresh. Continue?')) {
                    localStorage.removeItem('sffc_user_profile');
                    this.profile = {};
                    this.close();
                    if (typeof window.openProfileBuilder === 'function') {
                        window.openProfileBuilder();
                    }
                }
            });

            // Save and close
            $('#ps-save-close').on('click', () => {
                this.saveProfile();
                this.showSaveConfirmation();
                this.close();
            });
        }

        showSaveConfirmation() {
            // Brief toast notification
            const toast = $(`
                <div class="sffc-toast sffc-toast-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    Profile saved successfully
                </div>
            `);

            $('body').append(toast);
            setTimeout(() => toast.addClass('show'), 10);
            setTimeout(() => {
                toast.removeClass('show');
                setTimeout(() => toast.remove(), 300);
            }, 2500);
        }

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.profileSettings = new ProfileSettings();

        // Global function to open settings
        window.openProfileSettings = function() {
            window.profileSettings.open();
        };
    });

})(jQuery);
