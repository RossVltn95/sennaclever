/**
 * Materials Request Modal
 * Shows generated email template and material request options
 */

(function($) {
    'use strict';

    class SFFCMaterialsModal {
        constructor() {
            this.$modal = null;
            this.currentData = null;
            this.init();
        }

        init() {
            // Create modal HTML if it doesn't exist
            if (!$('#sffcMaterialsModal').length) {
                this.createModal();
            }
            this.$modal = $('#sffcMaterialsModal');
            this.bindEvents();
        }

        createModal() {
            const modalHtml = `
                <div id="sffcMaterialsModal" class="sffc-materials-modal" aria-hidden="true">
                    <div class="sffc-materials-overlay" data-materials-close></div>
                    <div class="sffc-materials-dialog">
                        <button type="button" class="sffc-materials-close" data-materials-action="close" aria-label="Close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>

                        <div class="sffc-materials-content">
                            <h3 class="sffc-materials-section-title">Request Application Materials</h3>
                            <p class="sffc-materials-note">Get professionally tailored materials for this role</p>

                            <div class="sffc-materials-options">
                                <button type="button" class="sffc-materials-option" data-material-type="cover_letter">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                    <span>Cover Letter</span>
                                </button>
                                <button type="button" class="sffc-materials-option" data-material-type="tailored_resume">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="12" y1="18" x2="12" y2="12"/>
                                        <line x1="9" y1="15" x2="15" y2="15"/>
                                    </svg>
                                    <span>Tailored Resume</span>
                                </button>
                            </div>

                            <div class="sffc-materials-divider"></div>

                            <h2 class="sffc-materials-title">Email Template</h2>
                            <p class="sffc-materials-subtitle">Copy this email and send it to the recruiter</p>

                            <div class="sffc-materials-email-box">
                                <div class="sffc-materials-email-content" id="sffcMaterialsEmailContent">
                                    <!-- Email template will be inserted here -->
                                </div>
                                <button type="button" class="sffc-materials-copy-btn" id="sffcMaterialsCopy">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copy Email
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
        }

        bindEvents() {
            const self = this;

            // Close button
            $(document).on('click', '[data-materials-action="close"]', function(e) {
                e.preventDefault();
                self.close();
            });

            // Overlay click
            $(document).on('click', '[data-materials-close]', function(e) {
                if (e.target === this) {
                    self.close();
                }
            });

            // Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$modal && self.$modal.attr('aria-hidden') === 'false') {
                    self.close();
                }
            });

            // Copy email button
            $(document).on('click', '#sffcMaterialsCopy', function() {
                const text = $('#sffcMaterialsEmailContent').text();
                navigator.clipboard.writeText(text).then(function() {
                    $(this).html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!');
                    setTimeout(() => {
                        $('#sffcMaterialsCopy').html('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copy Email');
                    }, 2000);
                });
            });

            // Material request buttons
            $(document).on('click', '.sffc-materials-option', function() {
                const materialType = $(this).data('materialType');
                const $btn = $(this);

                if (!self.currentData) return;

                $btn.prop('disabled', true).addClass('is-loading');

                $.post(window.sffcCRMLinkedIn.ajaxUrl, {
                    action: 'sffc_crm_request_app_pack',
                    nonce: window.sffcCRMLinkedIn.nonce,
                    doc_type: materialType,
                    post_id: self.currentData.postId,
                    role_title: self.currentData.roleTitle,
                    company: self.currentData.company,
                    recruiter_name: self.currentData.recruiterName,
                    recruiter_email: self.currentData.recruiterEmail
                }).done(function(response) {
                    if (response && response.success) {
                        $btn.html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span>Requested</span>');
                        setTimeout(() => {
                            $btn.prop('disabled', false).removeClass('is-loading');
                        }, 2000);
                    } else {
                        $btn.prop('disabled', false).removeClass('is-loading');
                        alert('Unable to request material. Please try again.');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).removeClass('is-loading');
                    alert('Unable to request material. Please try again.');
                });
            });
        }

        open(data) {
            this.currentData = data;

            // Generate email template
            const emailTemplate = this.generateEmailTemplate(data);
            $('#sffcMaterialsEmailContent').text(emailTemplate);

            // Show modal
            this.$modal.attr('aria-hidden', 'false');
            $('body').addClass('sffc-modal-open');
        }

        close() {
            this.$modal.attr('aria-hidden', 'true');
            $('body').removeClass('sffc-modal-open');
            this.currentData = null;
        }

        generateEmailTemplate(data) {
            const template = `Dear ${data.recruiterName},

I am writing to express my strong interest in the ${data.roleTitle} position at ${data.company}.

With my background in finance and relevant experience, I believe I would be a strong fit for this role. I am particularly drawn to ${data.company}'s reputation in the industry and would welcome the opportunity to contribute to your team.

I have attached my resume for your review and would be happy to discuss how my skills and experience align with your needs.

Thank you for your consideration. I look forward to hearing from you.

Best regards`;

            return template;
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        window.SFFCMaterialsModal = new SFFCMaterialsModal();
    });

})(jQuery);
