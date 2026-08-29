/**
 * Application Pack JavaScript
 * Handles modal interactions and document generation
 *
 * @package SFFC_Careers
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Application Pack Module
    const AppPack = {
        // State
        state: {
            currentJobId: null,
            currentPackType: 'cv',
            isGenerating: false,
            generatedContent: null,
            isModalOpen: false,
            isAnimating: false,
        },

        // Icons
        icons: {
            pack: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
            close: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>',
            cv: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>',
            coverLetter: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>',
            networking: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>',
            interview: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" /></svg>',
            company: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" /></svg>',
            fullPack: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>',
            download: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>',
            check: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>',
            spinner: '<svg class="sffc-app-pack-spinner-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>',
        },

        // Pack type configurations
        packTypes: {
            cv: { name: 'Tailored CV', icon: 'cv', credits: 1, formats: ['pdf', 'docx'], description: 'ATS-optimized CV tailored to this role' },
            cover_letter: { name: 'Cover Letter', icon: 'coverLetter', credits: 1, formats: ['pdf', 'docx'], description: 'Professionally crafted letter' },
            networking: { name: 'Networking', icon: 'networking', credits: 0, formats: ['text'], description: 'LinkedIn & email templates' },
            interview_prep: { name: 'Interview Prep', icon: 'interview', credits: 1, formats: ['pdf'], description: 'Questions & talking points' },
            company_brief: { name: 'Company Intel', icon: 'company', credits: 1, formats: ['pdf'], description: 'Research & insights brief' },
            full_pack: { name: 'Full Pack', icon: 'fullPack', credits: 3, formats: ['zip'], description: 'Complete application bundle' },
        },

        /**
         * Initialize the module
         */
        init: function() {
            this.createModal();
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Trigger button click (from job cards)
            $(document).on('click', '.sffc-app-pack-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const jobId = $(this).data('job-id');
                self.openModal(jobId);
            });

            // Close button click - direct handler
            $(document).on('click', '.sffc-app-pack-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeModal();
            });

            // Overlay click to close (only when clicking directly on overlay)
            $(document).on('click', '.sffc-app-pack-modal-overlay', function(e) {
                // Only close if clicking directly on the overlay, not on modal content
                if ($(e.target).hasClass('sffc-app-pack-modal-overlay')) {
                    self.closeModal();
                }
            });

            // Escape key to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.state.isModalOpen) {
                    self.closeModal();
                }
            });

            // Pack type selection with smooth transition
            $(document).on('click', '.sffc-app-pack-type', function(e) {
                e.preventDefault();
                if (self.state.isAnimating || self.state.isGenerating) return;

                const type = $(this).data('type');
                if (type !== self.state.currentPackType) {
                    self.selectPackType(type);
                }
            });

            // Keyboard navigation for pack types
            $(document).on('keydown', '.sffc-app-pack-type', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }

                // Arrow key navigation
                const $types = $('.sffc-app-pack-type');
                const currentIndex = $types.index($(this));

                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    const nextIndex = (currentIndex + 1) % $types.length;
                    $types.eq(nextIndex).focus().click();
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    const prevIndex = (currentIndex - 1 + $types.length) % $types.length;
                    $types.eq(prevIndex).focus().click();
                }
            });

            // Generate button
            $(document).on('click', '.sffc-app-pack-generate', function(e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    self.generatePack();
                }
            });

            // Download button
            $(document).on('click', '.sffc-app-pack-download', function(e) {
                e.preventDefault();
                if (!$(this).prop('disabled')) {
                    const format = $('.sffc-app-pack-format-select select').val() || 'pdf';
                    self.downloadPack(format);
                }
            });

            // Prevent modal content clicks from bubbling
            $(document).on('click', '.sffc-app-pack-modal', function(e) {
                e.stopPropagation();
            });
        },

        /**
         * Create modal structure
         */
        createModal: function() {
            if ($('#sffc-app-pack-modal').length) return;

            const credits = typeof sffcAppPack !== 'undefined' ? sffcAppPack.credits : '—';

            const modal = `
                <div class="sffc-app-pack-modal-overlay" id="sffc-app-pack-modal" role="dialog" aria-modal="true" aria-labelledby="sffc-modal-title">
                    <div class="sffc-app-pack-modal">
                        <div class="sffc-app-pack-header">
                            <h2 id="sffc-modal-title">${this.icons.pack} Application Pack</h2>
                            <button class="sffc-app-pack-close" type="button" aria-label="Close modal" title="Close">
                                ${this.icons.close}
                            </button>
                        </div>
                        <div class="sffc-app-pack-types" role="tablist" aria-label="Document types">
                            ${this.renderPackTypes()}
                        </div>
                        <div class="sffc-app-pack-content">
                            <div class="sffc-app-pack-preview-container">
                                <div class="sffc-app-pack-initial">
                                    <div class="sffc-app-pack-initial-content">
                                        <div class="sffc-app-pack-initial-icon">${this.icons.pack}</div>
                                        <h3>Generate Your Application Materials</h3>
                                        <p>Select a document type above and click Generate to create tailored application materials for this role.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sffc-app-pack-footer">
                            <div class="sffc-app-pack-credits-info">
                                Credits: <strong>${credits}</strong> remaining
                            </div>
                            <div class="sffc-app-pack-actions">
                                <div class="sffc-app-pack-format-select">
                                    <select aria-label="Output format">
                                        <option value="pdf">PDF</option>
                                        <option value="docx">Word (DOCX)</option>
                                    </select>
                                </div>
                                <button class="sffc-app-pack-btn sffc-app-pack-btn--primary sffc-app-pack-generate" type="button">
                                    Generate
                                </button>
                                <button class="sffc-app-pack-btn sffc-app-pack-btn--download sffc-app-pack-download" type="button" disabled>
                                    ${this.icons.download} Download
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
        },

        /**
         * Render pack type buttons
         */
        renderPackTypes: function() {
            let html = '';
            let index = 0;

            for (const [key, config] of Object.entries(this.packTypes)) {
                const activeClass = key === 'cv' ? 'active' : '';
                const creditText = config.credits === 0 ? 'Free' : `${config.credits} credit${config.credits > 1 ? 's' : ''}`;
                const tabIndex = key === 'cv' ? '0' : '-1';

                html += `
                    <button class="sffc-app-pack-type ${activeClass}"
                            data-type="${key}"
                            type="button"
                            role="tab"
                            aria-selected="${key === 'cv' ? 'true' : 'false'}"
                            tabindex="${tabIndex}"
                            title="${config.description}">
                        <div class="sffc-app-pack-type-icon">${this.icons[config.icon]}</div>
                        <div class="sffc-app-pack-type-info">
                            <div class="sffc-app-pack-type-name">${config.name}</div>
                            <div class="sffc-app-pack-type-credits">${creditText}</div>
                        </div>
                    </button>
                `;
                index++;
            }
            return html;
        },

        /**
         * Open modal
         */
        openModal: function(jobId) {
            const self = this;

            // Prevent opening if already animating
            if (this.state.isAnimating) return;

            // Check if user is logged in
            if (typeof sffcAppPack !== 'undefined' && !sffcAppPack.isLoggedIn) {
                if (confirm('Please log in to use Application Pack. Would you like to log in now?')) {
                    window.location.href = sffcAppPack.loginUrl;
                }
                return;
            }

            // Check if user has access (when restrictions are enabled)
            if (typeof sffcAppPack !== 'undefined' && sffcAppPack.isRestricted && !sffcAppPack.hasAccess) {
                this.showUpgradeModal();
                return;
            }

            // Set state
            this.state.currentJobId = jobId;
            this.state.generatedContent = null;
            this.state.isAnimating = true;

            // Reset state
            this.selectPackType('cv', false); // false = don't animate content change
            $('.sffc-app-pack-download').prop('disabled', true);

            // Update credits display
            this.updateCreditsDisplay();

            // Reset preview container
            $('.sffc-app-pack-preview-container').html(`
                <div class="sffc-app-pack-initial">
                    <div class="sffc-app-pack-initial-content">
                        <div class="sffc-app-pack-initial-icon">${this.icons.pack}</div>
                        <h3>Generate Your Application Materials</h3>
                        <p>Select a document type above and click Generate to create tailored application materials for this role.</p>
                    </div>
                </div>
            `);

            // Show modal with animation
            const $modal = $('#sffc-app-pack-modal');
            $modal.addClass('active');
            $('body').addClass('sffc-modal-open');

            // Focus management
            setTimeout(function() {
                self.state.isModalOpen = true;
                self.state.isAnimating = false;
                // Focus the first pack type for keyboard nav
                $modal.find('.sffc-app-pack-type.active').focus();
            }, 300);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            const self = this;

            // Prevent closing if already animating
            if (this.state.isAnimating) return;

            this.state.isAnimating = true;

            const $modal = $('#sffc-app-pack-modal');
            $modal.removeClass('active');

            // Wait for animation to complete
            setTimeout(function() {
                $('body').removeClass('sffc-modal-open');
                self.state.isModalOpen = false;
                self.state.isGenerating = false;
                self.state.isAnimating = false;
            }, 300);
        },

        /**
         * Show upgrade modal for non-members
         */
        showUpgradeModal: function() {
            const self = this;

            const modal = `
                <div class="sffc-app-pack-modal-overlay active" id="sffc-app-pack-upgrade-modal" role="dialog" aria-modal="true">
                    <div class="sffc-app-pack-modal" style="max-width: 500px;">
                        <div class="sffc-app-pack-header">
                            <h2>${this.icons.pack} Upgrade Required</h2>
                            <button class="sffc-app-pack-close" type="button" aria-label="Close">${this.icons.close}</button>
                        </div>
                        <div class="sffc-app-pack-content">
                            <div class="sffc-app-pack-upgrade">
                                <h3>Unlock Application Pack</h3>
                                <p>Get professionally tailored CVs, cover letters, and interview prep materials for every job application.</p>
                                <ul>
                                    <li>${this.icons.check} <strong>Tailored CV</strong> - ATS-optimized for each role</li>
                                    <li>${this.icons.check} <strong>Cover Letter</strong> - Professionally crafted</li>
                                    <li>${this.icons.check} <strong>Interview Prep</strong> - Role-specific questions</li>
                                    <li>${this.icons.check} <strong>Company Intel</strong> - Research brief</li>
                                </ul>
                                <a href="${typeof sffcAppPack !== 'undefined' ? sffcAppPack.upgradeUrl : '/membership/'}" class="sffc-app-pack-upgrade-btn">
                                    View Membership Options
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14"></path>
                                        <path d="m12 5 7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modal);
            $('body').addClass('sffc-modal-open');

            // Bind close handlers for upgrade modal
            $('#sffc-app-pack-upgrade-modal').on('click', function(e) {
                if ($(e.target).hasClass('sffc-app-pack-modal-overlay')) {
                    self.closeUpgradeModal();
                }
            });

            $('#sffc-app-pack-upgrade-modal .sffc-app-pack-close').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeUpgradeModal();
            });
        },

        /**
         * Close upgrade modal
         */
        closeUpgradeModal: function() {
            const $modal = $('#sffc-app-pack-upgrade-modal');
            $modal.removeClass('active');

            setTimeout(function() {
                $modal.remove();
                $('body').removeClass('sffc-modal-open');
            }, 300);
        },

        /**
         * Update credits display
         */
        updateCreditsDisplay: function() {
            if (typeof sffcAppPack !== 'undefined') {
                if (sffcAppPack.creditSystemEnabled) {
                    $('.sffc-app-pack-credits-info').html(
                        'Credits: <strong>' + sffcAppPack.credits + '</strong> remaining'
                    );
                } else {
                    $('.sffc-app-pack-credits-info').html('<strong>Unlimited</strong> access');
                }
            }
        },

        /**
         * Select pack type with smooth transition
         */
        selectPackType: function(type, animate = true) {
            const self = this;

            if (this.state.isAnimating && animate) return;

            this.state.currentPackType = type;

            // Update visual state
            $('.sffc-app-pack-type')
                .removeClass('active')
                .attr('aria-selected', 'false')
                .attr('tabindex', '-1');

            $(`.sffc-app-pack-type[data-type="${type}"]`)
                .addClass('active')
                .attr('aria-selected', 'true')
                .attr('tabindex', '0');

            // Update format options based on type
            const config = this.packTypes[type];
            const formats = config?.formats || ['pdf', 'docx'];
            let optionsHtml = '';

            if (formats.includes('pdf')) optionsHtml += '<option value="pdf">PDF</option>';
            if (formats.includes('docx')) optionsHtml += '<option value="docx">Word (DOCX)</option>';
            if (formats.includes('zip')) optionsHtml += '<option value="zip">ZIP Archive</option>';

            // Handle networking (text-only, copy instead of download)
            if (type === 'networking') {
                $('.sffc-app-pack-format-select').addClass('hidden');
                $('.sffc-app-pack-download').addClass('hidden');
            } else {
                $('.sffc-app-pack-format-select').removeClass('hidden').find('select').html(optionsHtml);
                $('.sffc-app-pack-download').removeClass('hidden');
            }

            // Reset generated content when changing types
            if (animate && this.state.generatedContent) {
                this.state.generatedContent = null;
                $('.sffc-app-pack-download').prop('disabled', true);

                // Animate content change
                const $preview = $('.sffc-app-pack-preview-container');
                $preview.addClass('fade-out');

                setTimeout(function() {
                    $preview.html(`
                        <div class="sffc-app-pack-initial">
                            <div class="sffc-app-pack-initial-content">
                                <div class="sffc-app-pack-initial-icon">${self.icons[config.icon]}</div>
                                <h3>${config.name}</h3>
                                <p>${config.description}</p>
                                <p class="sffc-app-pack-initial-hint">Click <strong>Generate</strong> to create this document</p>
                            </div>
                        </div>
                    `);
                    $preview.removeClass('fade-out');
                }, 200);
            }
        },

        /**
         * Generate pack
         */
        generatePack: function() {
            if (this.state.isGenerating) return;

            const self = this;
            const packType = this.state.currentPackType;
            const config = this.packTypes[packType];

            this.state.isGenerating = true;

            // Show loading with branded animation
            $('.sffc-app-pack-preview-container').html(`
                <div class="sffc-app-pack-loading">
                    <div class="sffc-app-pack-spinner"></div>
                    <div class="sffc-app-pack-loading-text">
                        <strong>Generating your ${config.name}</strong>
                        <span>This may take a moment...</span>
                    </div>
                </div>
            `);

            $('.sffc-app-pack-generate')
                .prop('disabled', true)
                .html('<span class="sffc-btn-spinner"></span> Generating...');

            // Make AJAX request
            $.ajax({
                url: sffcAppPack.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_generate_app_pack',
                    nonce: sffcAppPack.nonce,
                    job_id: this.state.currentJobId,
                    pack_type: packType,
                },
                success: function(response) {
                    self.state.isGenerating = false;
                    $('.sffc-app-pack-generate').prop('disabled', false).text('Generate');

                    if (response.success) {
                        self.state.generatedContent = response.data;
                        self.showPreview(response.data.preview_html);

                        // Enable download (except for networking)
                        if (packType !== 'networking') {
                            $('.sffc-app-pack-download').prop('disabled', false);
                        }

                        // Update credits if returned
                        if (response.data.credits_remaining !== undefined) {
                            sffcAppPack.credits = response.data.credits_remaining;
                            self.updateCreditsDisplay();
                        }
                    } else {
                        self.showError(response.data?.message || 'Generation failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    self.state.isGenerating = false;
                    $('.sffc-app-pack-generate').prop('disabled', false).text('Generate');

                    let errorMsg = 'Network error. Please check your connection and try again.';
                    if (xhr.status === 403) {
                        errorMsg = 'Session expired. Please refresh the page and try again.';
                    } else if (xhr.status === 429) {
                        errorMsg = 'Too many requests. Please wait a moment and try again.';
                    }

                    self.showError(errorMsg);
                }
            });
        },

        /**
         * Show preview with smooth transition
         */
        showPreview: function(html) {
            const $container = $('.sffc-app-pack-preview-container');

            $container.addClass('fade-out');

            setTimeout(function() {
                $container.html(html);
                $container.removeClass('fade-out');
            }, 150);
        },

        /**
         * Show error with retry option
         */
        showError: function(message) {
            const self = this;

            $('.sffc-app-pack-preview-container').html(`
                <div class="sffc-app-pack-error">
                    <div class="sffc-app-pack-error-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h3>Something went wrong</h3>
                    <p>${message}</p>
                    <button class="sffc-app-pack-btn sffc-app-pack-btn--secondary sffc-app-pack-retry" type="button">
                        Try Again
                    </button>
                </div>
            `);

            // Bind retry click
            $('.sffc-app-pack-retry').on('click', function() {
                self.generatePack();
            });
        },

        /**
         * Download pack
         */
        downloadPack: function(format) {
            if (!this.state.generatedContent) {
                this.showToast('Please generate the document first.', 'warning');
                return;
            }

            const self = this;
            const $downloadBtn = $('.sffc-app-pack-download');

            $downloadBtn.prop('disabled', true).html('<span class="sffc-btn-spinner"></span> Preparing...');

            $.ajax({
                url: sffcAppPack.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_download_app_pack',
                    nonce: sffcAppPack.nonce,
                    job_id: this.state.currentJobId,
                    pack_type: this.state.currentPackType,
                    format: format,
                },
                success: function(response) {
                    $downloadBtn.prop('disabled', false).html(self.icons.download + ' Download');

                    if (response.success && response.data.download_url) {
                        // Create temporary link and trigger download
                        const link = document.createElement('a');
                        link.href = response.data.download_url;
                        link.download = response.data.filename || 'application-pack';
                        link.target = '_blank';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        self.showToast('Download started!', 'success');
                    } else {
                        self.showToast(response.data?.message || 'Download failed. Please try again.', 'error');
                    }
                },
                error: function() {
                    $downloadBtn.prop('disabled', false).html(self.icons.download + ' Download');
                    self.showToast('Network error. Please try again.', 'error');
                }
            });
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info') {
            // Remove existing toast
            $('.sffc-app-pack-toast').remove();

            const iconMap = {
                success: this.icons.check,
                error: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>',
                warning: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>',
                info: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>',
            };

            const $toast = $(`
                <div class="sffc-app-pack-toast sffc-app-pack-toast--${type}">
                    <span class="sffc-app-pack-toast-icon">${iconMap[type] || iconMap.info}</span>
                    <span class="sffc-app-pack-toast-message">${message}</span>
                </div>
            `);

            $('body').append($toast);

            // Trigger animation
            setTimeout(function() {
                $toast.addClass('show');
            }, 10);

            // Auto remove
            setTimeout(function() {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, 3000);
        },
    };

    /**
     * Toolkit Handler
     * Manages the inline toolkit section on job listings
     */
    const ToolkitHandler = {
        state: {
            isAnimating: false,
        },

        /**
         * Initialize toolkit functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            const self = this;

            // Expand/collapse button
            $(document).on('click', '.sffc-toolkit__expand-btn', function(e) {
                e.preventDefault();
                self.toggleExpand($(this));
            });

            // Document card click (via card or action button)
            $(document).on('click', '.sffc-toolkit__doc, .sffc-toolkit__doc-action', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Don't trigger if clicking the upgrade link
                if ($(e.target).closest('.sffc-toolkit__upgrade-link').length) {
                    return;
                }

                // Get the parent doc card
                const $doc = $(this).hasClass('sffc-toolkit__doc') ? $(this) : $(this).closest('.sffc-toolkit__doc');
                const docId = $doc.data('doc-id');
                const hasAccess = $doc.data('has-access') === true || $doc.data('has-access') === 'true';
                const jobId = $doc.closest('.sffc-toolkit').data('job-id');

                if (hasAccess) {
                    self.generateDocument(jobId, docId);
                } else {
                    self.showUpgradePrompt($doc);
                }
            });
        },

        /**
         * Toggle expand/collapse for hidden documents
         */
        toggleExpand: function($btn) {
            if (this.state.isAnimating) return;

            const self = this;
            const $wrapper = $btn.closest('.sffc-toolkit');
            const $hiddenDocs = $wrapper.find('.sffc-toolkit__documents--hidden');
            const isExpanded = $hiddenDocs.hasClass('expanded');
            const docCount = $hiddenDocs.find('.sffc-toolkit__doc').length;

            this.state.isAnimating = true;

            if (isExpanded) {
                $hiddenDocs.removeClass('expanded');
                $btn.removeClass('expanded');
                $btn.find('span').first().text('Show ' + docCount + ' more documents');
            } else {
                $hiddenDocs.addClass('expanded');
                $btn.addClass('expanded');
                $btn.find('span').first().text('Show less');
            }

            setTimeout(function() {
                self.state.isAnimating = false;
            }, 300);
        },

        /**
         * Generate document directly
         */
        generateDocument: function(jobId, docId) {
            const packType = docId;

            // Store current state
            AppPack.state.currentJobId = jobId;
            AppPack.state.currentPackType = packType;

            // Open modal and auto-generate
            this.openModalAndGenerate(jobId, packType);
        },

        /**
         * Open modal and auto-generate
         */
        openModalAndGenerate: function(jobId, packType) {
            // Check login first
            if (typeof sffcAppPack !== 'undefined' && !sffcAppPack.isLoggedIn) {
                if (confirm('Please log in to generate documents. Would you like to log in now?')) {
                    window.location.href = sffcAppPack.loginUrl;
                }
                return;
            }

            const config = AppPack.packTypes[packType];

            AppPack.state.currentJobId = jobId;
            AppPack.state.generatedContent = null;

            // Select the pack type without content animation
            AppPack.selectPackType(packType, false);

            // Update credits display
            if (typeof sffcAppPack !== 'undefined') {
                AppPack.updateCreditsDisplay();
            }

            // Show loading state in modal
            $('.sffc-app-pack-preview-container').html(`
                <div class="sffc-app-pack-loading">
                    <div class="sffc-app-pack-spinner"></div>
                    <div class="sffc-app-pack-loading-text">
                        <strong>Generating your ${config?.name || 'document'}</strong>
                        <span>This may take a moment...</span>
                    </div>
                </div>
            `);

            // Show modal
            const $modal = $('#sffc-app-pack-modal');
            $modal.addClass('active');
            $('body').addClass('sffc-modal-open');
            AppPack.state.isModalOpen = true;

            // Auto-trigger generation after modal animation
            setTimeout(function() {
                AppPack.generatePack();
            }, 350);
        },

        /**
         * Show upgrade prompt for locked documents
         */
        showUpgradePrompt: function($doc) {
            const self = this;
            const requiredTier = $doc.data('required-tier') || 'Pro';
            const upgradeUrl = $doc.data('upgrade-url') || '/membership/';
            const docName = $doc.find('.sffc-toolkit__doc-name').text().trim();

            // Create and show upgrade modal
            const modal = `
                <div class="sffc-app-pack-modal-overlay active" id="sffc-toolkit-upgrade-modal" role="dialog" aria-modal="true">
                    <div class="sffc-app-pack-modal" style="max-width: 450px;">
                        <div class="sffc-app-pack-header">
                            <h2>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                                Upgrade Required
                            </h2>
                            <button class="sffc-app-pack-close" type="button" aria-label="Close">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="sffc-app-pack-content" style="padding: 32px;">
                            <div style="text-align: center;">
                                <h3 style="font-size: 18px; font-weight: 600; color: #1f2937; margin: 0 0 12px;">
                                    Unlock ${docName}
                                </h3>
                                <p style="font-size: 14px; color: #6b7280; margin: 0 0 24px;">
                                    This document is available with the <strong style="color: #1e40af;">${requiredTier}</strong> tier and above.
                                </p>
                                <a href="${upgradeUrl}" class="sffc-toolkit__upgrade-btn" style="display: inline-flex; padding: 12px 24px; font-size: 14px;">
                                    View Upgrade Options
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px; margin-left: 6px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                    </svg>
                                </a>
                                <p style="font-size: 12px; color: #9ca3af; margin-top: 16px;">
                                    Upgrade anytime to access all premium documents
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if any
            $('#sffc-toolkit-upgrade-modal').remove();
            $('body').append(modal);
            $('body').addClass('sffc-modal-open');

            // Close on overlay click
            $('#sffc-toolkit-upgrade-modal').on('click', function(e) {
                if ($(e.target).hasClass('sffc-app-pack-modal-overlay')) {
                    self.closeToolkitUpgradeModal();
                }
            });

            // Close button
            $('#sffc-toolkit-upgrade-modal .sffc-app-pack-close').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeToolkitUpgradeModal();
            });

            // Close on escape
            $(document).one('keydown.toolkitUpgrade', function(e) {
                if (e.key === 'Escape') {
                    self.closeToolkitUpgradeModal();
                }
            });
        },

        /**
         * Close toolkit upgrade modal
         */
        closeToolkitUpgradeModal: function() {
            const $modal = $('#sffc-toolkit-upgrade-modal');
            $modal.removeClass('active');
            $(document).off('keydown.toolkitUpgrade');

            setTimeout(function() {
                $modal.remove();
                // Only remove body class if no other modals are open
                if (!$('#sffc-app-pack-modal').hasClass('active')) {
                    $('body').removeClass('sffc-modal-open');
                }
            }, 300);
        }
    };

    /**
     * Fullscreen Toolkit Modal
     * Shows the before/after comparison and document generation
     */
    const FullscreenToolkit = {
        state: {
            isOpen: false,
            jobData: null,
        },

        icons: {
            zap: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
            check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
            alertCircle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
            close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>',
            document: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>',
            mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>',
            clipboard: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>',
            building: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>',
            users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>',
            arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>',
        },

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // Fullscreen trigger button
            $(document).on('click', '.sffc-app-pack-fullscreen-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                self.state.jobData = {
                    jobId: $btn.data('job-id'),
                    jobTitle: $btn.data('job-title'),
                    company: $btn.data('company'),
                    location: $btn.data('location'),
                    matchScore: parseInt($btn.data('match-score')) || 52,
                };

                self.openModal();
            });

            // Close button
            $(document).on('click', '.sffc-fullscreen-toolkit__close', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Overlay click
            $(document).on('click', '.sffc-fullscreen-toolkit', function(e) {
                if ($(e.target).hasClass('sffc-fullscreen-toolkit')) {
                    self.closeModal();
                }
            });

            // Escape key
            $(document).on('keydown.fullscreenToolkit', function(e) {
                if (e.key === 'Escape' && self.state.isOpen) {
                    self.closeModal();
                }
            });

            // Document generation buttons
            $(document).on('click', '.sffc-fullscreen-toolkit__doc-btn', function(e) {
                e.preventDefault();
                const docType = $(this).data('doc-type');
                self.generateDocument(docType);
            });
        },

        openModal: function() {
            if (this.state.isOpen) return;

            const data = this.state.jobData;
            const currentScore = data.matchScore;
            const improvedScore = Math.min(currentScore + 35, 95);

            const modalHtml = `
                <div class="sffc-fullscreen-toolkit" id="sffc-fullscreen-toolkit">
                    <div class="sffc-fullscreen-toolkit__container">
                        <!-- Header -->
                        <div class="sffc-fullscreen-toolkit__header">
                            <button type="button" class="sffc-fullscreen-toolkit__close" aria-label="Close">
                                ${this.icons.close}
                            </button>
                            <div class="sffc-fullscreen-toolkit__badge">
                                ${this.icons.zap}
                                <span>Application Pack</span>
                            </div>
                            <h1 class="sffc-fullscreen-toolkit__title">Your Personalized Application Toolkit</h1>
                            <p class="sffc-fullscreen-toolkit__subtitle">
                                Tailored specifically for <strong>${data.jobTitle}</strong> at <strong>${data.company}</strong>
                            </p>
                        </div>

                        <!-- Before/After Comparison -->
                        <div class="sffc-fullscreen-toolkit__comparison">
                            <div class="sffc-fullscreen-toolkit__card sffc-fullscreen-toolkit__card--before">
                                <div class="sffc-fullscreen-toolkit__card-label">
                                    <span class="sffc-fullscreen-toolkit__dot sffc-fullscreen-toolkit__dot--red"></span>
                                    Without Application Pack
                                </div>
                                <div class="sffc-fullscreen-toolkit__score">
                                    <div class="sffc-fullscreen-toolkit__score-circle sffc-fullscreen-toolkit__score-circle--low">
                                        <span>${currentScore}%</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__score-label">Current Match</span>
                                </div>
                                <ul class="sffc-fullscreen-toolkit__list">
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--negative">
                                        ${this.icons.alertCircle}
                                        <span>Generic cover letter</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--negative">
                                        ${this.icons.alertCircle}
                                        <span>Missing ATS keywords</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--negative">
                                        ${this.icons.alertCircle}
                                        <span>Skill gaps not addressed</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--negative">
                                        ${this.icons.alertCircle}
                                        <span>~15% response rate</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="sffc-fullscreen-toolkit__arrow">
                                ${this.icons.arrow}
                            </div>

                            <div class="sffc-fullscreen-toolkit__card sffc-fullscreen-toolkit__card--after">
                                <div class="sffc-fullscreen-toolkit__card-label">
                                    <span class="sffc-fullscreen-toolkit__dot sffc-fullscreen-toolkit__dot--green"></span>
                                    With Application Pack
                                </div>
                                <div class="sffc-fullscreen-toolkit__score">
                                    <div class="sffc-fullscreen-toolkit__score-circle sffc-fullscreen-toolkit__score-circle--high">
                                        <span>${improvedScore}%</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__score-label">Improved Match</span>
                                </div>
                                <ul class="sffc-fullscreen-toolkit__list">
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--positive">
                                        ${this.icons.check}
                                        <span>Tailored cover letter</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--positive">
                                        ${this.icons.check}
                                        <span>ATS-optimized CV</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--positive">
                                        ${this.icons.check}
                                        <span>Gaps addressed strategically</span>
                                    </li>
                                    <li class="sffc-fullscreen-toolkit__list-item sffc-fullscreen-toolkit__list-item--positive">
                                        ${this.icons.check}
                                        <span>~47% response rate</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- What's Included -->
                        <div class="sffc-fullscreen-toolkit__section">
                            <h2 class="sffc-fullscreen-toolkit__section-title">What's in Your Toolkit</h2>
                            <div class="sffc-fullscreen-toolkit__docs">
                                <button type="button" class="sffc-fullscreen-toolkit__doc-btn" data-doc-type="cv">
                                    <div class="sffc-fullscreen-toolkit__doc-icon">${this.icons.document}</div>
                                    <div class="sffc-fullscreen-toolkit__doc-info">
                                        <span class="sffc-fullscreen-toolkit__doc-name">Tailored CV</span>
                                        <span class="sffc-fullscreen-toolkit__doc-desc">ATS-optimized for this role</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__doc-badge">1 credit</span>
                                </button>

                                <button type="button" class="sffc-fullscreen-toolkit__doc-btn" data-doc-type="cover_letter">
                                    <div class="sffc-fullscreen-toolkit__doc-icon">${this.icons.mail}</div>
                                    <div class="sffc-fullscreen-toolkit__doc-info">
                                        <span class="sffc-fullscreen-toolkit__doc-name">Cover Letter</span>
                                        <span class="sffc-fullscreen-toolkit__doc-desc">Professionally crafted</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__doc-badge">1 credit</span>
                                </button>

                                <button type="button" class="sffc-fullscreen-toolkit__doc-btn" data-doc-type="interview_prep">
                                    <div class="sffc-fullscreen-toolkit__doc-icon">${this.icons.clipboard}</div>
                                    <div class="sffc-fullscreen-toolkit__doc-info">
                                        <span class="sffc-fullscreen-toolkit__doc-name">Interview Prep</span>
                                        <span class="sffc-fullscreen-toolkit__doc-desc">Questions & talking points</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__doc-badge">1 credit</span>
                                </button>

                                <button type="button" class="sffc-fullscreen-toolkit__doc-btn" data-doc-type="company_brief">
                                    <div class="sffc-fullscreen-toolkit__doc-icon">${this.icons.building}</div>
                                    <div class="sffc-fullscreen-toolkit__doc-info">
                                        <span class="sffc-fullscreen-toolkit__doc-name">Company Intel</span>
                                        <span class="sffc-fullscreen-toolkit__doc-desc">Research & insights brief</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__doc-badge">1 credit</span>
                                </button>

                                <button type="button" class="sffc-fullscreen-toolkit__doc-btn sffc-fullscreen-toolkit__doc-btn--free" data-doc-type="networking">
                                    <div class="sffc-fullscreen-toolkit__doc-icon">${this.icons.users}</div>
                                    <div class="sffc-fullscreen-toolkit__doc-info">
                                        <span class="sffc-fullscreen-toolkit__doc-name">Networking Messages</span>
                                        <span class="sffc-fullscreen-toolkit__doc-desc">LinkedIn & email templates</span>
                                    </div>
                                    <span class="sffc-fullscreen-toolkit__doc-badge sffc-fullscreen-toolkit__doc-badge--free">Free</span>
                                </button>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div class="sffc-fullscreen-toolkit__cta">
                            <button type="button" class="sffc-fullscreen-toolkit__cta-btn" data-doc-type="full_pack">
                                ${this.icons.zap}
                                Generate Full Application Pack
                            </button>
                            <p class="sffc-fullscreen-toolkit__cta-note">Uses 3 credits • Includes all documents</p>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('body').addClass('sffc-modal-open');

            // Trigger animation
            setTimeout(() => {
                $('#sffc-fullscreen-toolkit').addClass('active');
                this.state.isOpen = true;
            }, 10);
        },

        closeModal: function() {
            const self = this;
            const $modal = $('#sffc-fullscreen-toolkit');

            $modal.removeClass('active');

            setTimeout(function() {
                $modal.remove();
                $('body').removeClass('sffc-modal-open');
                self.state.isOpen = false;
            }, 300);
        },

        generateDocument: function(docType) {
            const self = this;
            const data = this.state.jobData;

            // Close fullscreen toolkit
            this.closeModal();

            // Set AppPack state and open the generation modal
            setTimeout(function() {
                AppPack.state.currentJobId = data.jobId;
                AppPack.state.currentPackType = docType;
                AppPack.state.generatedContent = null;

                // Select pack type
                AppPack.selectPackType(docType, false);

                // Update credits
                AppPack.updateCreditsDisplay();

                // Show loading in modal
                $('.sffc-app-pack-preview-container').html(`
                    <div class="sffc-app-pack-loading">
                        <div class="sffc-app-pack-spinner"></div>
                        <div class="sffc-app-pack-loading-text">
                            <strong>Generating your ${AppPack.packTypes[docType]?.name || 'document'}</strong>
                            <span>This may take a moment...</span>
                        </div>
                    </div>
                `);

                // Open modal and generate
                $('#sffc-app-pack-modal').addClass('active');
                $('body').addClass('sffc-modal-open');
                AppPack.state.isModalOpen = true;

                setTimeout(function() {
                    AppPack.generatePack();
                }, 350);
            }, 350);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        AppPack.init();
        ToolkitHandler.init();
        FullscreenToolkit.init();
    });

    // Expose globally for external access
    window.AppPack = AppPack;
    window.ToolkitHandler = ToolkitHandler;
    window.FullscreenToolkit = FullscreenToolkit;

})(jQuery);
