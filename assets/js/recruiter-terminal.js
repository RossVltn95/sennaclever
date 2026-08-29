/**
 * Recruiter Terminal JavaScript
 *
 * Handles step navigation, AJAX operations, and interactive features.
 *
 * @package SennaFinanceCareer
 * @subpackage RecruiterTerminal
 */

(function($) {
    'use strict';

    // Ensure recruiterTerminal config exists
    if (typeof recruiterTerminal === 'undefined') {
        console.error('Recruiter Terminal: Configuration not loaded');
        return;
    }

    /**
     * Main Terminal Controller
     */
    var RecruiterTerminal = {
        // State
        currentStep: 1,
        campaignId: 0,
        selectedCandidates: [],
        matchedCandidates: [],

        /**
         * Initialize the terminal
         */
        init: function() {
            var $terminal = $('.rt-terminal');
            if (!$terminal.length) return;

            this.campaignId = $terminal.data('campaign-id') || 0;
            this.currentStep = parseInt($terminal.data('step')) || 1;

            // Polling properties
            this.pollInterval = null;
            this.lastFeedItemId = 0;
            this.campaignStatus = $terminal.data('campaign-status') || '';

            this.bindEvents();
            this.initializeStep(this.currentStep);

            // Start polling if viewing an active campaign detail
            var $detail = $('.rt-detail');
            if ($detail.length && this.campaignStatus === 'active') {
                this.campaignId = $detail.data('campaign-id') || this.campaignId;
                this.startPolling();
            }
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Navigation buttons
            $(document).on('click', '[data-action="next-step"]', function(e) {
                e.preventDefault();
                self.nextStep();
            });

            $(document).on('click', '[data-action="prev-step"]', function(e) {
                e.preventDefault();
                self.prevStep();
            });

            // Progress step clicks
            $(document).on('click', '.rt-progress__step--completed', function() {
                var step = $(this).data('step');
                if (step < self.currentStep) {
                    self.goToStep(step);
                }
            });

            // Campaign actions
            $(document).on('click', '[data-action="save-draft"]', function(e) {
                e.preventDefault();
                self.saveDraft();
            });

            $(document).on('click', '[data-action="submit-campaign"]', function(e) {
                e.preventDefault();
                self.submitCampaign();
            });

            $(document).on('click', '[data-action="delete"]', function(e) {
                e.preventDefault();
                var campaignId = $(this).data('campaign-id');
                self.deleteCampaign(campaignId);
            });

            $(document).on('click', '[data-action="pause-campaign"]', function(e) {
                e.preventDefault();
                self.pauseCampaign();
            });

            $(document).on('click', '[data-action="resume-campaign"]', function(e) {
                e.preventDefault();
                self.resumeCampaign();
            });

            // Candidate selection
            $(document).on('click', '[data-action="select-all"]', function(e) {
                e.preventDefault();
                self.selectAllCandidates();
            });

            $(document).on('click', '[data-action="deselect-all"]', function(e) {
                e.preventDefault();
                self.deselectAllCandidates();
            });

            $(document).on('change', '.rt-candidate__checkbox input', function() {
                self.updateSelectionCount();
            });

            // Email composer
            $(document).on('click', '.rt-variable-btn', function(e) {
                e.preventDefault();
                var variable = $(this).data('variable');
                self.insertVariable(variable);
            });

            $(document).on('input', '#outreach-subject, #outreach-message', function() {
                self.updatePreview();
            });

            $(document).on('click', '[data-action="refresh-preview"]', function(e) {
                e.preventDefault();
                self.updatePreview();
            });

            // Test email
            $(document).on('click', '[data-action="send-test"]', function(e) {
                e.preventDefault();
                self.showTestEmailModal();
            });

            $(document).on('click', '[data-action="confirm-test-email"]', function(e) {
                e.preventDefault();
                self.sendTestEmail();
            });

            // Schedule options
            $(document).on('change', 'input[name="schedule_type"]', function() {
                var type = $(this).val();
                if (type === 'scheduled') {
                    $('#rt-schedule-datetime').slideDown(200);
                } else {
                    $('#rt-schedule-datetime').slideUp(200);
                }
                self.updateSidebarSchedule();
            });

            // Schedule date/time changes
            $(document).on('change', '#schedule-date, #schedule-time', function() {
                self.updateSidebarSchedule();
            });

            // Sidebar real-time updates for form fields
            $(document).on('input', '#campaign-title', function() {
                var value = $(this).val().trim();
                $('#sidebar-title').text(value || '—');
            });

            $(document).on('input', '#candidate-brief', function() {
                var value = $(this).val().trim();
                if (value.length > 100) {
                    value = value.substring(0, 100) + '...';
                }
                $('#sidebar-brief').text(value || '—');
            });

            // Modal
            $(document).on('click', '[data-action="close-modal"], .rt-modal__backdrop', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            // Panel toggle
            $(document).on('click', '[data-action="toggle-panel"]', function(e) {
                e.preventDefault();
                $(this).closest('.rt-panel').toggleClass('rt-panel--expanded');
            });

            // Activity refresh
            $(document).on('click', '[data-action="refresh-activity"]', function(e) {
                e.preventDefault();
                self.refreshActivity();
            });

            // Export
            $(document).on('click', '[data-action="export-results"]', function(e) {
                e.preventDefault();
                self.exportResults();
            });

            // Dashboard filters
            $(document).on('click', '.rt-filter', function(e) {
                e.preventDefault();
                var filter = $(this).data('filter');
                self.filterCampaigns(filter);

                // Update active state
                $('.rt-filter').removeClass('rt-filter--active');
                $(this).addClass('rt-filter--active');
            });
        },

        /**
         * Filter campaigns by status
         */
        filterCampaigns: function(status) {
            var $cards = $('.rt-campaign-grid .rt-card');

            if (status === 'all') {
                $cards.show();
            } else {
                $cards.each(function() {
                    var cardStatus = $(this).data('status');
                    if (cardStatus === status) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        },

        /**
         * Initialize a specific step
         */
        initializeStep: function(step) {
            switch(step) {
                case 2:
                    this.findMatches();
                    break;
                case 3:
                    this.loadCandidatesForSelection();
                    break;
                case 4:
                    this.updatePreview();
                    break;
                case 5:
                    this.updateReviewSummary();
                    break;
            }
        },

        /**
         * Navigate to next step
         */
        nextStep: function() {
            var self = this;

            // Validate current step
            if (!this.validateStep(this.currentStep)) {
                return;
            }

            // Save current step data
            this.saveStepData(this.currentStep, function(success) {
                if (success) {
                    self.goToStep(self.currentStep + 1);
                }
            });
        },

        /**
         * Navigate to previous step
         */
        prevStep: function() {
            if (this.currentStep > 1) {
                this.goToStep(this.currentStep - 1);
            }
        },

        /**
         * Go to a specific step
         */
        goToStep: function(step) {
            if (step < 1 || step > 5) return;

            // Update step classes
            $('.rt-step').removeClass('rt-step--active');
            $('.rt-step[data-step="' + step + '"]').addClass('rt-step--active');

            // Update progress
            $('.rt-progress__step').each(function() {
                var stepNum = $(this).data('step');
                $(this).removeClass('rt-progress__step--active rt-progress__step--completed');

                if (stepNum < step) {
                    $(this).addClass('rt-progress__step--completed');
                } else if (stepNum === step) {
                    $(this).addClass('rt-progress__step--active');
                }
            });

            // Update progress bar
            var progress = (step / 5) * 100;
            $('.rt-progress__fill').css('width', progress + '%');

            // Update current step
            this.currentStep = step;
            $('input[name="current_step"]').val(step);

            // Initialize the new step
            this.initializeStep(step);

            // Scroll to top
            $('.rt-main').scrollTop(0);
        },

        /**
         * Validate a step before proceeding
         */
        validateStep: function(step) {
            var valid = true;
            var message = '';

            switch(step) {
                case 1:
                    var title = $('#campaign-title').val().trim();
                    var brief = $('#candidate-brief').val().trim();

                    if (!title) {
                        message = recruiterTerminal.strings.enterTitle;
                        valid = false;
                    } else if (!brief) {
                        message = recruiterTerminal.strings.enterBrief;
                        valid = false;
                    }
                    break;

                case 3:
                    this.collectSelectedCandidates();
                    if (this.selectedCandidates.length === 0) {
                        message = recruiterTerminal.strings.selectCandidates;
                        valid = false;
                    }
                    break;

                case 5:
                    var scheduleType = $('input[name="schedule_type"]:checked').val();
                    if (scheduleType === 'scheduled') {
                        var date = $('#schedule-date').val();
                        if (!date) {
                            message = recruiterTerminal.strings.scheduleRequired;
                            valid = false;
                        }
                    }
                    break;
            }

            if (!valid && message) {
                this.showNotification(message, 'error');
            }

            return valid;
        },

        /**
         * Save step data
         */
        saveStepData: function(step, callback) {
            var self = this;
            var data = this.collectFormData();
            data.action = 'rt_save_draft';
            data.nonce = recruiterTerminal.nonce;
            data.step = step;

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (response.data.campaign_id) {
                            self.campaignId = response.data.campaign_id;
                            $('input[name="campaign_id"]').val(self.campaignId);
                            $('.rt-creator').attr('data-campaign-id', self.campaignId);
                        }
                        callback(true);
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                        callback(false);
                    }
                },
                error: function() {
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                    callback(false);
                }
            });
        },

        /**
         * Collect form data
         */
        collectFormData: function() {
            var data = {
                campaign_id: this.campaignId,
                title: $('#campaign-title').val(),
                brief: $('#candidate-brief').val(),
                target_seniority: $('#target-seniority').val(),
                target_location: $('#target-location').val(),
                outreach_subject: $('#outreach-subject').val(),
                outreach_message: $('#outreach-message').val(),
                selected_candidates: this.selectedCandidates
            };

            return data;
        },

        /**
         * Find matching candidates
         */
        findMatches: function() {
            var self = this;
            var $searching = $('.rt-match-status__searching');
            var $results = $('.rt-match-status__results');
            var $preview = $('#rt-candidates-preview');

            $searching.show();
            $results.hide();
            $preview.html('');

            var data = {
                action: 'rt_find_matches',
                nonce: recruiterTerminal.nonce,
                brief: $('#candidate-brief').val(),
                seniority: $('#target-seniority').val(),
                location: $('#target-location').val()
            };

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    $searching.hide();
                    $results.show();

                    if (response.success && response.data.candidates) {
                        self.matchedCandidates = response.data.candidates;
                        $('.rt-match-status__count').text(response.data.candidates.length);

                        // Render preview
                        self.renderCandidatesPreview(response.data.candidates);
                    } else {
                        $('.rt-match-status__count').text('0');
                        $preview.html('<p class="rt-match-empty">' + recruiterTerminal.strings.noMatches + '</p>');
                    }
                },
                error: function() {
                    $searching.hide();
                    $results.show();
                    $('.rt-match-status__count').text('0');
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Render candidates preview
         */
        renderCandidatesPreview: function(candidates) {
            var self = this;
            var $preview = $('#rt-candidates-preview');
            var html = '';

            candidates.slice(0, 5).forEach(function(candidate) {
                html += '<div class="rt-candidate-preview">';
                html += '<div class="rt-candidate-preview__name">' + self.escapeHtml(candidate.name) + '</div>';
                html += '<div class="rt-candidate-preview__title">' + self.escapeHtml(candidate.title || '') + '</div>';
                html += '</div>';
            });

            if (candidates.length > 5) {
                html += '<div class="rt-candidate-preview rt-candidate-preview--more">';
                html += '+ ' + (candidates.length - 5) + ' more candidates';
                html += '</div>';
            }

            $preview.html(html);
        },

        /**
         * Load candidates for selection
         */
        loadCandidatesForSelection: function() {
            var self = this;
            var $list = $('#rt-candidates-list');

            if (this.matchedCandidates.length === 0) {
                $list.html('<p class="rt-no-candidates">No candidates to display. Go back to find matches first.</p>');
                return;
            }

            var html = '';
            this.matchedCandidates.forEach(function(candidate) {
                var checked = self.selectedCandidates.indexOf(candidate.user_id) !== -1 ? 'checked' : '';
                var matchScore = candidate.match_score || 0;

                html += '<div class="rt-candidate" data-user-id="' + candidate.user_id + '" data-match-score="' + matchScore + '">';
                html += '<label class="rt-candidate__checkbox">';
                html += '<input type="checkbox" name="candidates[]" value="' + candidate.user_id + '" ' + checked + '>';
                html += '<span class="rt-candidate__checkmark"></span>';
                html += '</label>';
                html += '<div class="rt-candidate__info">';
                html += '<div class="rt-candidate__name">' + self.escapeHtml(candidate.name) + '</div>';
                html += '<div class="rt-candidate__title">' + self.escapeHtml(candidate.title || '') + '</div>';
                html += '<div class="rt-candidate__company">' + self.escapeHtml(candidate.company || '') + '</div>';
                html += '<div class="rt-candidate__location">' + self.escapeHtml(candidate.location || '') + '</div>';
                html += '</div>';
                html += '<div class="rt-candidate__score">';
                html += '<span class="rt-candidate__score-value">' + matchScore + '%</span>';
                html += '<span class="rt-candidate__score-label">Match</span>';
                html += '</div>';
                html += '</div>';
            });

            $list.html(html);
            this.updateSelectionCount();
        },

        /**
         * Select all candidates
         */
        selectAllCandidates: function() {
            $('.rt-candidates-list input[type="checkbox"]').prop('checked', true);
            this.updateSelectionCount();
        },

        /**
         * Deselect all candidates
         */
        deselectAllCandidates: function() {
            $('.rt-candidates-list input[type="checkbox"]').prop('checked', false);
            this.updateSelectionCount();
        },

        /**
         * Update selection count
         */
        updateSelectionCount: function() {
            this.collectSelectedCandidates();
            var count = this.selectedCandidates.length;
            $('.rt-selection-bar__count').text(count);
            $('#review-candidate-count').text(count);

            // Update sidebar
            $('#sidebar-candidate-count').text(count);

            // Calculate and update average score
            var avgScore = this.calculateAverageScore();
            $('#sidebar-avg-score').text(avgScore);
        },

        /**
         * Calculate average match score of selected candidates
         */
        calculateAverageScore: function() {
            var totalScore = 0;
            var count = 0;

            $('.rt-candidates-list input[type="checkbox"]:checked').each(function() {
                var $card = $(this).closest('.rt-candidate');
                var score = parseInt($card.data('match-score'), 10) || 0;
                totalScore += score;
                count++;
            });

            return count > 0 ? Math.round(totalScore / count) : 0;
        },

        /**
         * Update sidebar schedule display
         */
        updateSidebarSchedule: function() {
            var scheduleType = $('input[name="schedule_type"]:checked').val();

            if (scheduleType === 'now') {
                $('#sidebar-schedule').text(recruiterTerminal.strings.sendImmediately || 'Send immediately');
            } else {
                var date = $('#schedule-date').val();
                var time = $('#schedule-time').val();

                if (date && time) {
                    var dateObj = new Date(date + 'T' + time);
                    var options = { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' };
                    $('#sidebar-schedule').text(dateObj.toLocaleDateString('en-US', options));
                } else {
                    $('#sidebar-schedule').text(recruiterTerminal.strings.notScheduled || 'Not scheduled');
                }
            }
        },

        /**
         * Collect selected candidates
         */
        collectSelectedCandidates: function() {
            this.selectedCandidates = [];
            var self = this;

            $('.rt-candidates-list input[type="checkbox"]:checked').each(function() {
                self.selectedCandidates.push($(this).val());
            });
        },

        /**
         * Insert variable into message
         */
        insertVariable: function(variable) {
            var $textarea = $('#outreach-message');
            var text = $textarea.val();
            var cursorPos = $textarea[0].selectionStart;

            var before = text.substring(0, cursorPos);
            var after = text.substring(cursorPos);

            $textarea.val(before + variable + after);
            $textarea.focus();

            // Set cursor position after variable
            var newPos = cursorPos + variable.length;
            $textarea[0].setSelectionRange(newPos, newPos);

            this.updatePreview();
        },

        /**
         * Update email preview
         */
        updatePreview: function() {
            var subject = $('#outreach-subject').val() || '';
            var body = $('#outreach-message').val() || '';

            // Replace variables with sample data
            var replacements = {
                '{{candidate_name}}': 'John Smith',
                '{{candidate_title}}': 'Senior Manager',
                '{{candidate_company}}': 'Example Corp',
                '{{role_title}}': $('#campaign-title').val() || 'Role Title',
                '{{recruiter_name}}': 'Your Name'
            };

            for (var key in replacements) {
                subject = subject.split(key).join(replacements[key]);
                body = body.split(key).join(replacements[key]);
            }

            $('#preview-subject').text(subject);
            $('#preview-body').text(body);
        },

        /**
         * Update review summary
         */
        updateReviewSummary: function() {
            $('#review-title').text($('#campaign-title').val());
            $('#review-brief').text($('#candidate-brief').val().substring(0, 150) + '...');
            this.updateSelectionCount();
        },

        /**
         * Show test email modal
         */
        showTestEmailModal: function() {
            $('#rt-test-email-modal').fadeIn(200);
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.rt-modal').fadeOut(200);
        },

        /**
         * Send test email
         */
        sendTestEmail: function() {
            var self = this;
            var email = $('#test-email-address').val().trim();

            if (!email || !this.isValidEmail(email)) {
                this.showNotification('Please enter a valid email address', 'error');
                return;
            }

            var $btn = $('[data-action="confirm-test-email"]');
            $btn.prop('disabled', true).text('Sending...');

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_send_test_email',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: this.campaignId,
                    email: email
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Send Test');

                    if (response.success) {
                        self.showNotification('Test email sent to ' + email, 'success');
                        self.closeModal();
                    } else {
                        self.showNotification(response.data.message || 'Failed to send test email', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Send Test');
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Save draft
         */
        saveDraft: function() {
            var self = this;
            this.collectSelectedCandidates();

            var data = this.collectFormData();
            data.action = 'rt_save_draft';
            data.nonce = recruiterTerminal.nonce;
            data.step = this.currentStep;

            this.showNotification(recruiterTerminal.strings.savingDraft, 'info');

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        if (response.data.campaign_id) {
                            self.campaignId = response.data.campaign_id;
                            $('input[name="campaign_id"]').val(self.campaignId);
                        }
                        self.showNotification(recruiterTerminal.strings.draftSaved, 'success');
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                    }
                },
                error: function() {
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Submit campaign for approval
         */
        submitCampaign: function() {
            var self = this;

            if (!this.validateStep(5)) {
                return;
            }

            this.collectSelectedCandidates();

            var scheduleType = $('input[name="schedule_type"]:checked').val();
            var scheduledAt = null;

            if (scheduleType === 'scheduled') {
                var date = $('#schedule-date').val();
                var time = $('#schedule-time').val() || '09:00';
                scheduledAt = date + ' ' + time + ':00';
            }

            var data = this.collectFormData();
            data.action = 'rt_submit_campaign';
            data.nonce = recruiterTerminal.nonce;
            data.scheduled_at = scheduledAt;

            var $btn = $('[data-action="submit-campaign"]');
            $btn.prop('disabled', true);
            this.showNotification(recruiterTerminal.strings.submitting, 'info');

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    $btn.prop('disabled', false);

                    if (response.success) {
                        self.showNotification('Campaign submitted for approval!', 'success');
                        // Redirect to dashboard after short delay
                        setTimeout(function() {
                            window.location.href = recruiterTerminal.baseUrl + '?post_type=page&p=' + self.getPageId();
                        }, 1500);
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Delete campaign
         */
        deleteCampaign: function(campaignId) {
            var self = this;

            if (!confirm(recruiterTerminal.strings.confirmDelete)) {
                return;
            }

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_delete_campaign',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: campaignId
                },
                success: function(response) {
                    if (response.success) {
                        $('[data-campaign-id="' + campaignId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                        self.showNotification('Campaign deleted', 'success');
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                    }
                },
                error: function() {
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Pause campaign
         */
        pauseCampaign: function() {
            var self = this;

            if (!confirm(recruiterTerminal.strings.confirmPause)) {
                return;
            }

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_pause_campaign',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: this.campaignId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                    }
                },
                error: function() {
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Resume campaign
         */
        resumeCampaign: function() {
            var self = this;

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_resume_campaign',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: this.campaignId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        self.showNotification(response.data.message || recruiterTerminal.strings.errorGeneric, 'error');
                    }
                },
                error: function() {
                    self.showNotification(recruiterTerminal.strings.errorGeneric, 'error');
                }
            });
        },

        /**
         * Refresh activity feed
         */
        refreshActivity: function() {
            var self = this;
            var $feed = $('#rt-activity-feed');

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_get_activity',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: this.campaignId
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        $feed.html(response.data.html);
                    }
                }
            });
        },

        /**
         * Start polling for feed updates (for active campaigns)
         */
        startPolling: function() {
            var self = this;

            // Only poll for active campaigns
            if (this.campaignStatus !== 'active') {
                return;
            }

            // Clear any existing interval
            this.stopPolling();

            // Poll every 10 seconds
            this.pollInterval = setInterval(function() {
                self.pollFeed();
            }, 10000);

            // Initial poll
            this.pollFeed();
        },

        /**
         * Stop polling
         */
        stopPolling: function() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        /**
         * Poll for feed updates
         */
        pollFeed: function() {
            var self = this;

            if (!this.campaignId || this.campaignStatus !== 'active') {
                return;
            }

            $.ajax({
                url: recruiterTerminal.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rt_get_feed',
                    nonce: recruiterTerminal.nonce,
                    campaign_id: this.campaignId,
                    last_id: this.lastFeedItemId || 0
                },
                success: function(response) {
                    if (response.success && response.data) {
                        // Update stats if provided
                        if (response.data.stats) {
                            self.updateDetailStats(response.data.stats);
                        }

                        // Prepend new feed items if any
                        if (response.data.items && response.data.items.length > 0) {
                            self.prependFeedItems(response.data.items);

                            // Update last ID
                            if (response.data.items[0] && response.data.items[0].id) {
                                self.lastFeedItemId = response.data.items[0].id;
                            }
                        }
                    }
                }
            });
        },

        /**
         * Prepend new feed items to the activity feed
         */
        prependFeedItems: function(items) {
            var $feed = $('#rt-activity-feed');
            var $empty = $feed.find('.rt-feed__empty');

            // Remove empty state if present
            if ($empty.length) {
                $empty.remove();
            }

            // Build HTML for new items
            items.reverse().forEach(function(item) {
                var iconHtml = '';
                var typeClass = '';

                switch (item.action_type) {
                    case 'email_sent':
                        iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>';
                        typeClass = 'sent';
                        break;
                    case 'email_opened':
                        iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
                        typeClass = 'opened';
                        break;
                    case 'response_interested':
                        iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>';
                        typeClass = 'interested';
                        break;
                    case 'response_not_interested':
                        iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>';
                        typeClass = 'not-interested';
                        break;
                    default:
                        iconHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
                        typeClass = 'default';
                }

                var actionText = item.action_type.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });

                var html = '<div class="rt-feed__item rt-feed__item--' + typeClass + ' rt-feed__item--new">' +
                    '<div class="rt-feed__icon">' + iconHtml + '</div>' +
                    '<div class="rt-feed__content">' +
                    (item.candidate_name ? '<span class="rt-feed__name">' + item.candidate_name + '</span>' : '') +
                    '<span class="rt-feed__action">' + actionText + '</span>' +
                    '<span class="rt-feed__time">Just now</span>' +
                    '</div></div>';

                $feed.prepend(html);
            });
        },

        /**
         * Update detail view stats
         */
        updateDetailStats: function(stats) {
            if (!stats) return;

            // Update stat card values
            $('.rt-stat-card__value').each(function() {
                var $label = $(this).siblings('.rt-stat-card__label');
                var label = $label.text().toLowerCase();

                if (label.indexOf('targeted') !== -1 && stats.total !== undefined) {
                    $(this).text(stats.total);
                } else if (label.indexOf('sent') !== -1 && stats.sent !== undefined) {
                    $(this).text(stats.sent);
                } else if (label.indexOf('opened') !== -1 && stats.opened !== undefined) {
                    $(this).text(stats.opened);
                } else if (label.indexOf('responded') !== -1 && stats.responded !== undefined) {
                    $(this).text(stats.responded);
                }
            });

            // Update rates
            if (stats.sent > 0) {
                var openRate = Math.round((stats.opened / stats.sent) * 100 * 10) / 10;
                var responseRate = Math.round((stats.responded / stats.sent) * 100 * 10) / 10;

                $('.rt-stat-card__rate-value').each(function() {
                    var $label = $(this).siblings('.rt-stat-card__rate-label');
                    var label = $label.text().toLowerCase();

                    if (label.indexOf('open') !== -1) {
                        $(this).text(openRate + '%');
                    } else if (label.indexOf('response') !== -1) {
                        $(this).text(responseRate + '%');
                    }
                });
            }
        },

        /**
         * Export results
         */
        exportResults: function() {
            var url = recruiterTerminal.ajaxUrl + '?action=rt_export_results&campaign_id=' + this.campaignId + '&nonce=' + recruiterTerminal.nonce;
            window.location.href = url;
        },

        /**
         * Get current page ID from URL
         */
        getPageId: function() {
            var url = window.location.href;
            var match = url.match(/[?&]p=(\d+)/);
            return match ? match[1] : '';
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            // Remove existing notifications
            $('.rt-notification').remove();

            var $notification = $('<div class="rt-notification rt-notification--' + type + '">' + this.escapeHtml(message) + '</div>');

            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('rt-notification--visible');
            }, 10);

            setTimeout(function() {
                $notification.removeClass('rt-notification--visible');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 4000);
        },

        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Add notification styles dynamically
    var notificationStyles = '\
        .rt-notification {\
            position: fixed;\
            bottom: 20px;\
            right: 20px;\
            padding: 14px 20px;\
            background: #18181b;\
            color: #fff;\
            border-radius: 8px;\
            font-size: 14px;\
            font-weight: 500;\
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);\
            z-index: 10001;\
            opacity: 0;\
            transform: translateY(20px);\
            transition: all 0.3s ease;\
        }\
        .rt-notification--visible {\
            opacity: 1;\
            transform: translateY(0);\
        }\
        .rt-notification--success {\
            background: #1e6b4a;\
        }\
        .rt-notification--error {\
            background: #b91c1c;\
        }\
        .rt-notification--info {\
            background: #1d4ed8;\
        }\
        .rt-match-empty, .rt-no-candidates {\
            text-align: center;\
            padding: 40px 20px;\
            color: #71717a;\
            font-size: 14px;\
        }\
        .rt-candidate-preview {\
            padding: 12px 0;\
            border-bottom: 1px solid #e4e4e7;\
        }\
        .rt-candidate-preview:last-child {\
            border-bottom: none;\
        }\
        .rt-candidate-preview__name {\
            font-weight: 600;\
            color: #0f2137;\
        }\
        .rt-candidate-preview__title {\
            font-size: 13px;\
            color: #71717a;\
        }\
        .rt-candidate-preview--more {\
            color: #1e6b4a;\
            font-weight: 500;\
        }\
    ';

    $('<style>').text(notificationStyles).appendTo('head');

    // Initialize on document ready
    $(document).ready(function() {
        RecruiterTerminal.init();
    });

    // Expose to global scope if needed
    window.RecruiterTerminal = RecruiterTerminal;

})(jQuery);
