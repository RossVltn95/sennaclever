/**
 * Recruiter Portal JavaScript
 * Handles the recruiter self-service portal functionality
 */

(function($) {
    'use strict';

    const RecruiterPortal = {
        container: null,
        token: null,
        currentFilter: 'all',
        introductions: [],

        init() {
            this.container = $('.sffc-recruiter-portal');
            if (!this.container.length) return;

            this.token = this.container.data('token');
            if (!this.token) {
                this.showError('Invalid access token. Please use the link from your email.');
                return;
            }

            this.bindEvents();
            this.loadIntroductions();
        },

        bindEvents() {
            // Filter change
            this.container.on('change', '.sffc-rp-filter-select', (e) => {
                this.currentFilter = $(e.target).val();
                this.renderIntroductions();
            });

            // Accept introduction
            this.container.on('click', '.sffc-rp-btn-accept', (e) => {
                e.preventDefault();
                const introId = $(e.target).closest('.sffc-rp-intro-card').data('intro-id');
                this.showResponseForm(introId, 'accept');
            });

            // Reject introduction
            this.container.on('click', '.sffc-rp-btn-reject', (e) => {
                e.preventDefault();
                const introId = $(e.target).closest('.sffc-rp-intro-card').data('intro-id');
                this.showResponseForm(introId, 'reject');
            });

            // Submit response
            this.container.on('click', '.sffc-rp-submit-response', (e) => {
                e.preventDefault();
                const $form = $(e.target).closest('.sffc-rp-response-form');
                this.submitResponse($form);
            });

            // Cancel response
            this.container.on('click', '.sffc-rp-cancel-response', (e) => {
                e.preventDefault();
                $(e.target).closest('.sffc-rp-response-form').remove();
                $(e.target).closest('.sffc-rp-intro-card').find('.sffc-rp-intro-actions').show();
            });

            // View CV
            this.container.on('click', '.sffc-rp-view-cv', (e) => {
                e.preventDefault();
                const cvUrl = $(e.target).data('cv-url');
                if (cvUrl) {
                    window.open(cvUrl, '_blank');
                }
            });
        },

        loadIntroductions() {
            this.showLoading();

            $.ajax({
                url: sffcRecruiterPortal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_recruiter_get_intros',
                    token: this.token,
                    nonce: sffcRecruiterPortal.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.introductions = response.data.introductions;
                        this.updateStats(response.data.stats);
                        this.renderIntroductions();
                    } else {
                        this.showError(response.data.message || 'Failed to load introductions');
                    }
                },
                error: () => {
                    this.showError('Network error. Please try again.');
                }
            });
        },

        showLoading() {
            this.container.find('.sffc-rp-content').html(`
                <div class="sffc-rp-loading">
                    <div class="sffc-rp-spinner"></div>
                    <p>Loading introductions...</p>
                </div>
            `);
        },

        showError(message) {
            this.container.find('.sffc-rp-content').html(`
                <div class="sffc-rp-empty">
                    <svg class="sffc-rp-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h3>Error</h3>
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `);
        },

        updateStats(stats) {
            // Build stats HTML if container is empty
            const $statsContainer = this.container.find('#sffc-rp-stats');
            if ($statsContainer.children().length === 0) {
                $statsContainer.html(`
                    <div class="sffc-rp-stat sffc-rp-stat-total">
                        <span class="sffc-rp-stat-value">${stats.total || 0}</span>
                        <span class="sffc-rp-stat-label">Total</span>
                    </div>
                    <div class="sffc-rp-stat sffc-rp-stat-pending">
                        <span class="sffc-rp-stat-value">${stats.awaiting || 0}</span>
                        <span class="sffc-rp-stat-label">Pending</span>
                    </div>
                    <div class="sffc-rp-stat sffc-rp-stat-accepted">
                        <span class="sffc-rp-stat-value">${stats.accepted || 0}</span>
                        <span class="sffc-rp-stat-label">Accepted</span>
                    </div>
                `);
            } else {
                $statsContainer.find('.sffc-rp-stat-total .sffc-rp-stat-value').text(stats.total || 0);
                $statsContainer.find('.sffc-rp-stat-pending .sffc-rp-stat-value').text(stats.awaiting || 0);
                $statsContainer.find('.sffc-rp-stat-accepted .sffc-rp-stat-value').text(stats.accepted || 0);
            }
        },

        renderIntroductions() {
            const filtered = this.filterIntroductions();

            if (filtered.length === 0) {
                this.container.find('.sffc-rp-content').html(this.renderEmpty());
                return;
            }

            const html = `
                <div class="sffc-rp-intro-list">
                    ${filtered.map(intro => this.renderIntroCard(intro)).join('')}
                </div>
            `;

            this.container.find('.sffc-rp-content').html(html);
        },

        filterIntroductions() {
            if (this.currentFilter === 'all') {
                return this.introductions;
            }
            return this.introductions.filter(intro => intro.status === this.currentFilter);
        },

        renderEmpty() {
            const messages = {
                'all': 'No introductions yet. New candidate introductions will appear here.',
                'sent': 'No pending introductions awaiting your response.',
                'accepted': 'No accepted introductions yet.',
                'rejected': 'No rejected introductions.'
            };

            return `
                <div class="sffc-rp-empty">
                    <svg class="sffc-rp-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <h3>No Introductions</h3>
                    <p>${messages[this.currentFilter] || messages['all']}</p>
                </div>
            `;
        },

        renderIntroCard(intro) {
            const statusClass = this.getStatusClass(intro.status);
            const statusLabel = this.getStatusLabel(intro.status);
            const matchClass = this.getMatchClass(intro.score);
            const isPending = intro.status === 'sent' || intro.status === 'awaiting_response';

            // Extract nested data
            const candidate = intro.candidate || {};
            const job = intro.job || {};

            return `
                <div class="sffc-rp-intro-card" data-intro-id="${intro.id}">
                    <div class="sffc-rp-intro-header">
                        <div class="sffc-rp-candidate-avatar">
                            ${candidate.avatar_url
                                ? `<img src="${this.escapeHtml(candidate.avatar_url)}" alt="">`
                                : this.getInitials(candidate.name)
                            }
                        </div>
                        <div class="sffc-rp-candidate-info">
                            <h3 class="sffc-rp-candidate-name">${this.escapeHtml(candidate.name)}</h3>
                            ${candidate.headline ? `<p class="sffc-rp-candidate-headline">${this.escapeHtml(candidate.headline)}</p>` : ''}
                            <div class="sffc-rp-candidate-meta">
                                ${candidate.location ? `<span><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> ${this.escapeHtml(candidate.location)}</span>` : ''}
                                ${candidate.experience ? `<span><svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> ${this.escapeHtml(candidate.experience)}</span>` : ''}
                            </div>
                        </div>
                        ${intro.score ? `<div class="sffc-rp-match-score ${matchClass}">${intro.score}% Match</div>` : ''}
                    </div>

                    <div class="sffc-rp-intro-body">
                        <div class="sffc-rp-job-info">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span>Applied for: <strong>${this.escapeHtml(job.title)}</strong>${job.company ? ` at ${this.escapeHtml(job.company)}` : ''}</span>
                        </div>

                        ${intro.message ? `
                            <div class="sffc-rp-message">
                                <div class="sffc-rp-message-label">Candidate Message</div>
                                ${this.escapeHtml(intro.message)}
                            </div>
                        ` : ''}
                    </div>

                    ${isPending ? `
                        <div class="sffc-rp-intro-actions">
                            <button class="sffc-rp-btn sffc-rp-btn-primary sffc-rp-btn-accept">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Accept & Respond
                            </button>
                            <button class="sffc-rp-btn sffc-rp-btn-danger sffc-rp-btn-reject">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Decline
                            </button>
                            ${intro.cv_url ? `
                                <button class="sffc-rp-btn sffc-rp-btn-secondary sffc-rp-view-cv" data-cv-url="${this.escapeHtml(intro.cv_url)}">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    View CV
                                </button>
                            ` : ''}
                        </div>
                    ` : `
                        <div class="sffc-rp-intro-actions">
                            <span class="sffc-rp-status sffc-rp-status-${statusClass}">
                                ${statusLabel}
                            </span>
                            ${intro.cv_url ? `
                                <button class="sffc-rp-btn sffc-rp-btn-secondary sffc-rp-view-cv" data-cv-url="${this.escapeHtml(intro.cv_url)}">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    View CV
                                </button>
                            ` : ''}
                        </div>
                    `}
                </div>
            `;
        },

        showResponseForm(introId, action) {
            const $card = this.container.find(`.sffc-rp-intro-card[data-intro-id="${introId}"]`);
            const $actions = $card.find('.sffc-rp-intro-actions');

            // Hide actions
            $actions.hide();

            // Remove any existing form
            $card.find('.sffc-rp-response-form').remove();

            const isAccept = action === 'accept';
            const placeholder = isAccept
                ? "I'd love to connect! Here's what happens next..."
                : "Thank you for your interest. Unfortunately...";
            const label = isAccept
                ? "Your message to the candidate:"
                : "Feedback for the candidate (optional):";

            const formHtml = `
                <div class="sffc-rp-response-form" data-action="${action}">
                    <label>${label}</label>
                    <textarea placeholder="${placeholder}" rows="4"></textarea>
                    <div class="sffc-rp-response-btns">
                        <button class="sffc-rp-btn ${isAccept ? 'sffc-rp-btn-primary' : 'sffc-rp-btn-danger'} sffc-rp-submit-response">
                            ${isAccept ? 'Send Acceptance' : 'Send Decline'}
                        </button>
                        <button class="sffc-rp-btn sffc-rp-btn-secondary sffc-rp-cancel-response">
                            Cancel
                        </button>
                    </div>
                </div>
            `;

            $card.append(formHtml);
            $card.find('.sffc-rp-response-form textarea').focus();
        },

        submitResponse($form) {
            const $card = $form.closest('.sffc-rp-intro-card');
            const introId = $card.data('intro-id');
            const action = $form.data('action');
            const message = $form.find('textarea').val().trim();
            const $btn = $form.find('.sffc-rp-submit-response');

            // Disable button
            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: sffcRecruiterPortal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sffc_recruiter_respond',
                    token: this.token,
                    nonce: sffcRecruiterPortal.nonce,
                    intro_id: introId,
                    response: action === 'accept' ? 'accepted' : 'rejected',
                    message: message
                },
                success: (response) => {
                    if (response.success) {
                        // Update local data
                        const intro = this.introductions.find(i => i.id == introId);
                        if (intro) {
                            intro.status = action === 'accept' ? 'accepted' : 'rejected';
                            intro.response_message = message;
                        }

                        // Update stats
                        this.updateStats(response.data.stats);

                        // Re-render
                        this.renderIntroductions();

                        // Show success message
                        this.showNotification(
                            action === 'accept'
                                ? 'Response sent! The candidate has been notified.'
                                : 'Decline sent. The candidate has been notified.'
                        );
                    } else {
                        $btn.prop('disabled', false).text(action === 'accept' ? 'Send Acceptance' : 'Send Decline');
                        this.showNotification(response.data.message || 'Failed to send response', 'error');
                    }
                },
                error: () => {
                    $btn.prop('disabled', false).text(action === 'accept' ? 'Send Acceptance' : 'Send Decline');
                    this.showNotification('Network error. Please try again.', 'error');
                }
            });
        },

        showNotification(message, type = 'success') {
            const $notification = $(`
                <div class="sffc-rp-notification sffc-rp-notification-${type}">
                    ${this.escapeHtml(message)}
                </div>
            `);

            this.container.prepend($notification);

            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 4000);
        },

        getStatusClass(status) {
            const map = {
                'accepted': 'accepted',
                'rejected': 'rejected',
                'sent': 'awaiting'
            };
            return map[status] || 'awaiting';
        },

        getStatusLabel(status) {
            const map = {
                'accepted': 'Accepted',
                'rejected': 'Declined',
                'sent': 'Awaiting Response'
            };
            return map[status] || status;
        },

        getMatchClass(score) {
            if (!score) return 'medium';
            if (score >= 80) return 'high';
            if (score >= 60) return 'medium';
            return 'low';
        },

        getInitials(name) {
            if (!name) return '?';
            return name.split(' ')
                .map(part => part.charAt(0).toUpperCase())
                .slice(0, 2)
                .join('');
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    $(document).ready(() => {
        RecruiterPortal.init();
    });

})(jQuery);
