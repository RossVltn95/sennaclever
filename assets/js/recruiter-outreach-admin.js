/**
 * Recruiter Outreach Admin JavaScript
 *
 * Handles admin panel interactions for managing recruiter submissions
 */

(function($) {
    'use strict';

    const OutreachAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.loadRecruiters();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Log Submission button
            $(document).on('click', '.sffc-log-submission-btn', this.openSubmissionModal.bind(this));

            // Add Recruiter button
            $(document).on('click', '.sffc-add-recruiter-btn', this.openRecruiterModal.bind(this));

            // Modal close buttons
            $(document).on('click', '.sffc-modal-close, .sffc-modal-cancel', this.closeModals.bind(this));

            // Click outside modal to close
            $(document).on('click', '.sffc-modal', function(e) {
                if ($(e.target).hasClass('sffc-modal')) {
                    OutreachAdmin.closeModals();
                }
            });

            // Submission form submit
            $('#sffc-submission-form').on('submit', this.submitSubmission.bind(this));

            // Recruiter form submit
            $('#sffc-recruiter-form').on('submit', this.submitRecruiter.bind(this));

            // Status update select
            $(document).on('change', '.sffc-update-status', this.updateSubmissionStatus.bind(this));

            // Create opportunity button
            $(document).on('click', '.sffc-create-opportunity-btn', this.createOpportunity.bind(this));

            // Recruiter search in select
            $('#submission-recruiter').on('focus', function() {
                if ($(this).find('option').length <= 1) {
                    OutreachAdmin.loadRecruiters();
                }
            });
        },

        /**
         * Open submission modal
         */
        openSubmissionModal: function(e) {
            const $btn = $(e.currentTarget);
            const campaignId = $btn.data('campaign-id');
            const userId = $btn.data('user-id');
            const userName = $btn.data('user-name');

            $('#submission-campaign-id').val(campaignId);
            $('#submission-user-id').val(userId);
            $('#submission-user-name').text(userName);
            $('#submission-notes').val('');
            $('#submission-recruiter').val('');

            $('#sffc-submission-modal').show();
        },

        /**
         * Open recruiter modal
         */
        openRecruiterModal: function() {
            $('#sffc-recruiter-form')[0].reset();
            $('#sffc-recruiter-modal').show();
        },

        /**
         * Close all modals
         */
        closeModals: function() {
            $('.sffc-modal').hide();
        },

        /**
         * Load recruiters into select
         */
        loadRecruiters: function() {
            $.ajax({
                url: sffcOutreachAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_admin_get_recruiters',
                    nonce: sffcOutreachAdmin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.recruiters) {
                        const $select = $('#submission-recruiter');
                        $select.find('option:not(:first)').remove();

                        response.data.recruiters.forEach(function(rec) {
                            $select.append(
                                $('<option>', {
                                    value: rec.id,
                                    text: rec.contact_name + ' - ' + rec.company
                                })
                            );
                        });
                    }
                }
            });
        },

        /**
         * Submit new submission
         */
        submitSubmission: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: sffcOutreachAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_admin_create_submission',
                    nonce: sffcOutreachAdmin.nonce,
                    campaign_id: $('#submission-campaign-id').val(),
                    user_id: $('#submission-user-id').val(),
                    recruiter_id: $('#submission-recruiter').val(),
                    notes: $('#submission-notes').val()
                },
                success: function(response) {
                    if (response.success) {
                        OutreachAdmin.showNotice('Submission logged successfully', 'success');
                        OutreachAdmin.closeModals();

                        // Update count in table
                        const campaignId = $('#submission-campaign-id').val();
                        const $countCell = $('tr[data-campaign-id="' + campaignId + '"] .sffc-count');
                        if ($countCell.length) {
                            $countCell.text(parseInt($countCell.text()) + 1);
                        }
                    } else {
                        OutreachAdmin.showNotice(response.data.message || 'Error', 'error');
                    }
                },
                error: function() {
                    OutreachAdmin.showNotice('Network error', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Log Submission');
                }
            });
        },

        /**
         * Submit new recruiter
         */
        submitRecruiter: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $btn = $form.find('button[type="submit"]');

            $btn.prop('disabled', true).text('Saving...');

            const formData = {
                action: 'sffc_admin_add_recruiter',
                nonce: sffcOutreachAdmin.nonce,
                contact_name: $('#recruiter-name').val(),
                contact_email: $('#recruiter-email').val(),
                company: $('#recruiter-company').val(),
                job_title: $('#recruiter-title').val(),
                linkedin_url: $('#recruiter-linkedin').val(),
                photo_url: $('#recruiter-photo').val(),
                specialties: $('#recruiter-specialties').val(),
                typical_roles: $('#recruiter-roles').val()
            };

            $.ajax({
                url: sffcOutreachAdmin.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        OutreachAdmin.showNotice('Recruiter added successfully', 'success');
                        OutreachAdmin.closeModals();

                        // Add to select and select it
                        if (response.data.recruiter) {
                            const rec = response.data.recruiter;
                            $('#submission-recruiter').append(
                                $('<option>', {
                                    value: rec.id,
                                    text: rec.contact_name + ' - ' + rec.company,
                                    selected: true
                                })
                            );
                        }

                        // Reload page if on recruiters tab
                        if (window.location.href.indexOf('tab=recruiters') > -1) {
                            window.location.reload();
                        }
                    } else {
                        OutreachAdmin.showNotice(response.data.message || 'Error', 'error');
                    }
                },
                error: function() {
                    OutreachAdmin.showNotice('Network error', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Add Recruiter');
                }
            });
        },

        /**
         * Update submission status
         */
        updateSubmissionStatus: function(e) {
            const $select = $(e.currentTarget);
            const submissionId = $select.data('submission-id');
            const newStatus = $select.val();

            $.ajax({
                url: sffcOutreachAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_admin_update_submission',
                    nonce: sffcOutreachAdmin.nonce,
                    submission_id: submissionId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        // Update status badge
                        const $row = $select.closest('tr');
                        const $badge = $row.find('.sffc-status');
                        $badge
                            .removeClass('sffc-status-sent sffc-status-viewed sffc-status-responded sffc-status-no_response sffc-status-declined')
                            .addClass('sffc-status-' + newStatus)
                            .text(sffcOutreachAdmin.statuses[newStatus] || newStatus);

                        // Show/hide create opportunity button
                        if (newStatus === 'responded') {
                            if (!$row.find('.sffc-create-opportunity-btn').length) {
                                $select.after(
                                    '<button type="button" class="button sffc-create-opportunity-btn" data-submission-id="' + submissionId + '">Create Opportunity</button>'
                                );
                            }
                        } else {
                            $row.find('.sffc-create-opportunity-btn').remove();
                        }

                        OutreachAdmin.showNotice('Status updated', 'success');
                    } else {
                        OutreachAdmin.showNotice(response.data.message || 'Error', 'error');
                    }
                },
                error: function() {
                    OutreachAdmin.showNotice('Network error', 'error');
                }
            });
        },

        /**
         * Create opportunity from submission
         */
        createOpportunity: function(e) {
            const $btn = $(e.currentTarget);
            const submissionId = $btn.data('submission-id');

            if (!confirm('Create an opportunity from this submission? The candidate will be notified.')) {
                return;
            }

            $btn.prop('disabled', true).text('Creating...');

            $.ajax({
                url: sffcOutreachAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_admin_create_opportunity',
                    nonce: sffcOutreachAdmin.nonce,
                    submission_id: submissionId
                },
                success: function(response) {
                    if (response.success) {
                        OutreachAdmin.showNotice('Opportunity created!', 'success');
                        $btn.text('Created').addClass('button-disabled');
                    } else {
                        OutreachAdmin.showNotice(response.data.message || 'Error', 'error');
                        $btn.prop('disabled', false).text('Create Opportunity');
                    }
                },
                error: function() {
                    OutreachAdmin.showNotice('Network error', 'error');
                    $btn.prop('disabled', false).text('Create Opportunity');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            // Remove existing notices
            $('.sffc-admin-notice').remove();

            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const $notice = $('<div class="notice ' + noticeClass + ' is-dismissible sffc-admin-notice"><p>' + message + '</p></div>');

            $('.sffc-outreach-admin h1').after($notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        if ($('.sffc-outreach-admin').length) {
            OutreachAdmin.init();
        }
    });

})(jQuery);
