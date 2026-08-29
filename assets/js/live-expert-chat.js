(function($) {
    'use strict';

    class LiveExpertChat {
        constructor() {
            this.$widget = null;
            this.$toggle = null;
            this.$panel = null;
            this.$messages = null;
            this.$templates = null;
            this.$form = null;
            this.$textarea = null;
            this.liveExpertSessionId = this.generateSessionId();
            this.liveExpertConversationId = null;
            this.liveExpertLastTimestamp = 0;
            this.liveExpertMessageIds = new Set();
            this.liveExpertPollInterval = null;
            this.liveExpertHasShownConfirmation = false;
            this.liveExpertHasSentFollowup = false;
            this.isLoggedIn = !!(window.sffcLiveExpertChat && window.sffcLiveExpertChat.isLoggedIn);
            this.hasMembership = !!(window.sffcLiveExpertChat && window.sffcLiveExpertChat.hasMembership);
            this.joinUrl = window.sffcLiveExpertChat && window.sffcLiveExpertChat.joinUrl ? window.sffcLiveExpertChat.joinUrl : '/memberships/';
            this.membershipUrl = window.sffcLiveExpertChat && window.sffcLiveExpertChat.membershipUrl ? window.sffcLiveExpertChat.membershipUrl : '';
            this.init();
        }

        generateSessionId() {
            const stored = localStorage.getItem('sffc_live_expert_session');
            if (stored) {
                return stored;
            }
            const newSession = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('sffc_live_expert_session', newSession);
            return newSession;
        }

        init() {
            this.$widget = $('[data-live-expert-chat]');

            if (!this.$widget.length) {
                return;
            }

            this.$toggle = this.$widget.find('[data-role="toggle"]');
            this.$panel = this.$widget.find('[data-role="panel"]');
            this.$messages = this.$widget.find('[data-role="messages"]');
            this.$templates = this.$widget.find('[data-role="templates"]');
            this.$form = this.$widget.find('[data-role="form"]');
            this.$textarea = this.$form.find('[data-role="input"]');
            this.$membershipModal = this.$widget.find('[data-role="membership-modal"]');
            this.$membershipFormWrapper = this.$membershipModal.length ? this.$membershipModal.find('[data-role="membership-form"]') : $();
            this.$membershipForms = this.$membershipModal.length ? this.$membershipModal.find('[data-plan-form]') : $();

            this.bindEvents();
            this.showWelcomeMessage();
            this.initDefaultQuestion();
        }

        initDefaultQuestion() {
            // Only for logged out users
            if (this.hasMembership) {
                return;
            }

            const $defaultQuestion = this.$messages.find('.sffc-live-expert-default-question .sffc-live-expert-message-text');
            const $queueStatus = this.$messages.find('.sffc-live-expert-queue-status .sffc-live-expert-message-text');

            if (!$defaultQuestion.length || !$queueStatus.length) {
                return;
            }

            const questions = [
                'Need help breaking into private equity?',
                'Are you preparing for private equity interviews?',
                'Need help strengthening your private equity application?',
                'Want guidance on networking with PE headhunters?',
                'Want to improve your PE CV and cover letter?',
                'Need help with a private equity case study?',
                'Struggling to land private equity interviews?',
                'Want help preparing for PE assessment centres?',
                'Looking for tips on private equity HireVue interviews?',
                'Need guidance on your private equity career path?',
                'Want to stand out in private equity applications?',
                'Looking for insider tips on private equity roles?',
                'Need help with LBO or investment memo prep?',
                'Want advice on private equity applications?',
                'Looking to improve your private equity technical skills?'
            ];

            // Pick a random question
            const randomQuestion = questions[Math.floor(Math.random() * questions.length)];
            $defaultQuestion.text(randomQuestion);

            // Pick a random queue position (1-5)
            const queuePosition = Math.floor(Math.random() * 5) + 1;
            $queueStatus.text('Connecting you with an expert... You\'re #' + queuePosition + ' in the queue');
        }

        showWelcomeMessage() {
            // Check if user has seen welcome message
            const hasSeenWelcome = localStorage.getItem('sffc_live_expert_welcome_seen');
            if (hasSeenWelcome) {
                return;
            }

            // Create welcome message notification
            const $welcome = $('<div class="sffc-live-expert-welcome">' +
                '<div class="sffc-live-expert-welcome-content">' +
                    '<p>👋 Need help with private equity recruiting? <strong>Chat with a PE expert now.</strong></p>' +
                '</div>' +
            '</div>');

            this.$widget.append($welcome);

            // Use Intersection Observer to detect when widget is in view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && !$welcome.hasClass('is-visible')) {
                        // Widget is in view, show message after 8 second delay
                        setTimeout(() => {
                            $welcome.addClass('is-visible');

                            // Auto-hide after 10 seconds
                            setTimeout(() => {
                                this.hideWelcomeMessage($welcome);
                            }, 10000);
                        }, 8000);

                        // Stop observing after first show
                        observer.unobserve(this.$widget[0]);
                    }
                });
            }, {
                threshold: 0.5 // Show when 50% of widget is visible
            });

            observer.observe(this.$widget[0]);

            // Click to dismiss
            $welcome.on('click', () => {
                this.hideWelcomeMessage($welcome);
            });
        }

        hideWelcomeMessage($welcome) {
            $welcome.removeClass('is-visible');
            setTimeout(() => {
                $welcome.remove();
                localStorage.setItem('sffc_live_expert_welcome_seen', 'true');
            }, 500);
        }

        bindEvents() {
            // Toggle chat panel
            this.$toggle.on('click', () => this.togglePanel());

            // Close button
            this.$widget.find('[data-role="close"]').on('click', () => this.closePanel());

            // Template buttons
            this.$templates.on('click', '.sffc-live-expert-template-btn', (e) => {
                e.preventDefault();
                if (!this.isLoggedIn) {
                    this.redirectToJoin();
                    return;
                }
                if (!this.hasMembership) {
                    this.openMembershipModal();
                    return;
                }
                const template = $(e.currentTarget).data('template');
                if (template) {
                    this.sendMessage(template);
                }
            });

            // Form submission
            this.$form.on('submit', (e) => {
                e.preventDefault();
                this.sendMessage();
            });

            this.$widget.on('click', '[data-live-expert-modal-close]', (e) => {
                e.preventDefault();
                this.closeMembershipModal();
            });

            this.$widget.on('click', '.sffc-live-expert-plan-select', (e) => {
                e.preventDefault();
                this.handlePlanSelect($(e.currentTarget));
            });

            // Confirmation actions
            this.$messages.on('click', '.sffc-live-expert-confirm-btn', (e) => {
                e.preventDefault();
                this.handleConfirmationResponse($(e.currentTarget));
            });

            // Auto-resize textarea
            this.$textarea.on('input', () => {
                this.$textarea.css('height', 'auto');
                this.$textarea.css('height', this.$textarea[0].scrollHeight + 'px');
            });

            // Submit on Enter (but not Shift+Enter for new lines)
            this.$textarea.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            // Close on ESC
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape') {
                    if (this.$membershipModal && this.$membershipModal.hasClass('is-visible')) {
                        this.closeMembershipModal();
                        return;
                    }
                    if (this.$panel.hasClass('is-visible')) {
                        this.closePanel();
                    }
                }
            });
        }

        togglePanel() {
            if (this.$panel.hasClass('is-visible')) {
                this.closePanel();
            } else {
                this.openPanel();
            }
        }

        openPanel() {
            this.$panel.addClass('is-visible');
            this.$widget.addClass('is-open');

            // For logged out users, remove notification badge when panel opens
            if (!this.hasMembership) {
                this.$toggle.find('.sffc-live-expert-notification-badge').fadeOut(300);
            } else {
                this.$textarea.focus();
                this.startPolling();
            }
        }

        open() {
            this.openPanel();
        }

        closePanel() {
            this.$panel.removeClass('is-visible');
            this.$widget.removeClass('is-open');
            this.stopPolling();
        }

        close() {
            this.closePanel();
        }

        sendMessage(messageOverride = null) {
            if (!this.isLoggedIn) {
                this.redirectToJoin();
                return;
            }
            if (!this.hasMembership) {
                this.openMembershipModal();
                return;
            }
            const source = typeof messageOverride === 'string' ? messageOverride : this.$textarea.val();
            const message = (source || '').trim();

            if (!message) {
                return;
            }

            const $sendBtn = this.$form.find('.sffc-live-expert-send');

            // Show user message immediately
            this.addUserMessage(message);
            if (!this.liveExpertHasShownConfirmation) {
                this.addConfirmationMessage(message);
                this.liveExpertHasShownConfirmation = true;
            }

            // Clear textarea
            this.$textarea.val('');
            this.$textarea.css('height', 'auto');

            // Hide templates after first message
            this.$templates.addClass('hidden');

            // Show loading state
            $sendBtn.addClass('is-sending');

            // Send AJAX request using FAST backend
            $.ajax({
                url: window.sffcLiveExpertChat?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'sffc_live_expert_send_fast',
                    nonce: window.sffcLiveExpertChat?.nonce || '',
                    session_id: this.liveExpertSessionId,
                    conversation_id: this.liveExpertConversationId || '',
                    message: message,
                    sender: 'user'
                },
                success: (response) => {
                    if (response.success && response.data) {
                        if (response.data.conversation_id) {
                            this.liveExpertConversationId = response.data.conversation_id;
                        }
                        // Start polling if not already started
                        if (!this.liveExpertPollInterval) {
                            this.startPolling();
                        }
                    } else {
                        this.showErrorMessage(response.data?.message || 'Failed to send message');
                    }
                },
                error: () => {
                    this.showErrorMessage('Network error. Please try again.');
                },
                complete: () => {
                    $sendBtn.removeClass('is-sending');
                }
            });
        }

        addUserMessage(message) {
            const $message = $(`
                <div class="sffc-live-expert-message user">
                    <div class="sffc-live-expert-message-bubble">
                        <p class="sffc-live-expert-message-text">${this.escapeHtml(message)}</p>
                        <span class="sffc-live-expert-message-time">${this.getCurrentTime()}</span>
                    </div>
                </div>
            `);

            this.$messages.append($message);
            this.scrollToBottom();
        }

        addConfirmationMessage(message) {
            const confirmationIntro = 'Just to check you want help with the following';
            const $message = $(
                `<div class="sffc-live-expert-message expert sffc-live-expert-confirmation">
                    <div class="sffc-live-expert-message-bubble">
                        <p class="sffc-live-expert-message-text">${this.escapeHtml(confirmationIntro)}</p>
                        <div class="sffc-live-expert-confirm-topic">&ldquo;${this.escapeHtml(message)}&rdquo;</div>
                        <div class="sffc-live-expert-confirm-actions" role="group" aria-label="Confirm topic">
                            <button type="button" class="sffc-live-expert-confirm-btn" data-response="yes">Yes</button>
                            <button type="button" class="sffc-live-expert-confirm-btn" data-response="no">No</button>
                        </div>
                        <span class="sffc-live-expert-message-time">${this.getCurrentTime()}</span>
                    </div>
                </div>
            `);

            this.$messages.append($message);
            this.scrollToBottom();
        }

        handleConfirmationResponse($button) {
            const $confirmation = $button.closest('.sffc-live-expert-confirmation');
            if (!$confirmation.length || $confirmation.hasClass('is-resolved')) {
                return;
            }

            $confirmation.addClass('is-resolved');
            $confirmation.find('.sffc-live-expert-confirm-actions').remove();

            this.addFollowUpMessage();
        }

        addFollowUpMessage() {
            if (this.liveExpertHasSentFollowup) {
                return;
            }

            // Generate random queue position (1-5)
            const queuePosition = Math.floor(Math.random() * 5) + 1;
            const followUpText = 'Connecting you with an expert... You\'re #' + queuePosition + ' in the queue';

            const $message = $(
                `<div class="sffc-live-expert-message expert sffc-live-expert-followup sffc-live-expert-queue-status">
                    <div class="sffc-live-expert-message-bubble">
                        <p class="sffc-live-expert-message-text">${this.escapeHtml(followUpText)}</p>
                        <span class="sffc-live-expert-message-time">${this.getCurrentTime()}</span>
                    </div>
                </div>
            `);

            this.$messages.append($message);
            this.scrollToBottom();
            this.liveExpertHasSentFollowup = true;
        }

        openMembershipModal() {
            if (!this.isLoggedIn) {
                this.redirectToJoin();
                return;
            }
            if (this.$membershipModal && this.$membershipModal.length) {
                this.$membershipModal.addClass('is-visible').attr('aria-hidden', 'false');
                $('body').addClass('sffc-live-expert-modal-open');
            } else if (this.membershipUrl) {
                window.open(this.membershipUrl, '_blank', 'noopener');
            }
        }

        redirectToJoin() {
            if (this.joinUrl) {
                window.location.href = this.joinUrl;
            }
        }

        closeMembershipModal() {
            if (!this.$membershipModal || !this.$membershipModal.length) {
                return;
            }
            this.$membershipModal.removeClass('is-visible').attr('aria-hidden', 'true');
            this.$membershipModal.find('[data-plan-card]').removeClass('is-active');
            if (this.$membershipFormWrapper && this.$membershipFormWrapper.length) {
                this.$membershipFormWrapper.attr('hidden', 'hidden');
            }
            if (this.$membershipForms && this.$membershipForms.length) {
                this.$membershipForms.attr('hidden', 'hidden');
            }
            $('body').removeClass('sffc-live-expert-modal-open');
        }

        handlePlanSelect($button) {
            if (!$button || !$button.length) {
                if (this.membershipUrl) {
                    window.open(this.membershipUrl, '_blank', 'noopener');
                }
                return;
            }

            const slug = $button.data('planSlug');
            const url = $button.data('planUrl');
            const hasShortcode = !!$button.data('planShortcode');

            if (this.$membershipModal) {
                this.$membershipModal.find('[data-plan-card]').removeClass('is-active');
                $button.closest('[data-plan-card]').addClass('is-active');
            }

            if (hasShortcode && slug && this.$membershipForms && this.$membershipForms.length) {
                if (this.$membershipFormWrapper && this.$membershipFormWrapper.length) {
                    this.$membershipFormWrapper.removeAttr('hidden');
                }
                this.$membershipForms.attr('hidden', 'hidden');
                const $target = this.$membershipForms.filter('[data-plan-form="' + slug + '"]');
                if ($target.length) {
                    $target.removeAttr('hidden');
                }
                return;
            }

            if (url) {
                window.open(url, '_blank', 'noopener');
            }
        }

        showSuccessMessage(message) {
            const $success = $(`
                <div class="sffc-live-expert-success">
                    <p>${this.escapeHtml(message)}</p>
                </div>
            `);

            this.$messages.append($success);
            this.scrollToBottom();
        }

        showErrorMessage(message) {
            const $error = $(`
                <div class="sffc-live-expert-message expert">
                    <div class="sffc-live-expert-message-bubble">
                        <p class="sffc-live-expert-message-text" style="color: #dc2626;">${this.escapeHtml(message)}</p>
                    </div>
                </div>
            `);

            this.$messages.append($error);
            this.scrollToBottom();
        }

        scrollToBottom() {
            this.$messages.scrollTop(this.$messages[0].scrollHeight);
        }

        getCurrentTime() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        startPolling() {
            if (this.liveExpertPollInterval) {
                return;
            }
            // Poll immediately
            this.fetchMessages();
            // Then poll every 5 seconds
            this.liveExpertPollInterval = setInterval(() => {
                this.fetchMessages();
            }, 5000);
        }

        stopPolling() {
            if (this.liveExpertPollInterval) {
                clearInterval(this.liveExpertPollInterval);
                this.liveExpertPollInterval = null;
            }
        }

        fetchMessages() {
            $.ajax({
                url: window.sffcLiveExpertChat?.ajaxUrl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'sffc_live_expert_fetch_fast',
                    nonce: window.sffcLiveExpertChat?.nonce || '',
                    session_id: this.liveExpertSessionId,
                    conversation_id: this.liveExpertConversationId || '',
                    since: this.liveExpertLastTimestamp || 0
                },
                success: (response) => {
                    if (response.success && response.data) {
                        if (response.data.conversation_id) {
                            this.liveExpertConversationId = response.data.conversation_id;
                        }
                        if (Array.isArray(response.data.messages)) {
                            this.processMessages(response.data.messages);
                        }
                    }
                }
            });
        }

        processMessages(messages) {
            messages.forEach((message) => {
                if (!message || !message.id) {
                    return;
                }
                // Skip duplicates
                if (this.liveExpertMessageIds.has(message.id)) {
                    return;
                }
                this.liveExpertMessageIds.add(message.id);

                // Update timestamp
                if (message.timestamp) {
                    const ts = Number(message.timestamp);
                    if (!isNaN(ts)) {
                        this.liveExpertLastTimestamp = Math.max(this.liveExpertLastTimestamp, ts);
                    }
                }

                // Only show expert messages (user messages are shown immediately)
                if (message.sender === 'expert') {
                    this.addExpertMessage(message.content, message.sender_name || 'Live Expert');
                }
            });
        }

        addExpertMessage(message, senderName) {
            const $message = $(`
                <div class="sffc-live-expert-message expert">
                    <div class="sffc-live-expert-message-bubble">
                        <p class="sffc-live-expert-message-text">${this.escapeHtml(message)}</p>
                        <span class="sffc-live-expert-message-time">${senderName} • ${this.getCurrentTime()}</span>
                    </div>
                </div>
            `);

            // Remove any success messages and queue status messages
            this.$messages.find('.sffc-live-expert-success').remove();
            this.$messages.find('.sffc-live-expert-queue-status').remove();
            this.$messages.append($message);
            this.scrollToBottom();
        }
    }

    // Initialize on DOM ready
    $(document).ready(function() {
        window.sffcLiveExpert = new LiveExpertChat();
    });

})(jQuery);
