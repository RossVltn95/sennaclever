/**
 * Recruiter Introduction Admin JS
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Quick approve from list view
        $('.sffc-approve-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');

            if (!confirm('Approve this introduction request?')) {
                return;
            }

            $btn.prop('disabled', true).text('Approving...');

            $.ajax({
                url: sffcIntroAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_update_intro_status',
                    nonce: sffcIntroAdmin.nonce,
                    id: id,
                    status: 'approved'
                },
                success: function(response) {
                    if (response.success) {
                        // Update row
                        var $row = $btn.closest('tr');
                        $row.find('.sffc-intro-status')
                            .removeClass()
                            .addClass('sffc-intro-status sffc-intro-status-approved')
                            .text(response.data.label);
                        $btn.remove();
                    } else {
                        alert(response.data?.message || 'Failed to approve');
                        $btn.prop('disabled', false).text('Approve');
                    }
                },
                error: function() {
                    alert('An error occurred');
                    $btn.prop('disabled', false).text('Approve');
                }
            });
        });

        // Status update buttons (single view)
        $('.sffc-status-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var status = $btn.data('status');
            var label = sffcIntroAdmin.status_labels[status] || status;

            if (!confirm('Update status to "' + label + '"?')) {
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: sffcIntroAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_update_intro_status',
                    nonce: sffcIntroAdmin.nonce,
                    id: id,
                    status: status
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated state
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to update status');
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred');
                    $btn.prop('disabled', false);
                }
            });
        });

        // Save pitch message
        $('.sffc-save-pitch-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var pitch = $('#pitch-message').val();

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: sffcIntroAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_add_pitch_message',
                    nonce: sffcIntroAdmin.nonce,
                    id: id,
                    pitch: pitch
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Saved!');
                        setTimeout(function() {
                            $btn.prop('disabled', false).text('Save Pitch');
                        }, 2000);
                    } else {
                        alert(response.data?.message || 'Failed to save');
                        $btn.prop('disabled', false).text('Save Pitch');
                    }
                },
                error: function() {
                    alert('An error occurred');
                    $btn.prop('disabled', false).text('Save Pitch');
                }
            });
        });

        // Record recruiter response
        $('.sffc-record-response-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var id = $btn.data('id');
            var outcome = $btn.data('outcome');
            var response = $('#recruiter-response').val();
            var feedback = $('#recruiter-feedback').val();

            var confirmMsg = outcome === 'accepted'
                ? 'Mark this introduction as accepted?'
                : 'Mark this introduction as rejected?';

            if (!confirm(confirmMsg)) {
                return;
            }

            $btn.prop('disabled', true);
            $btn.siblings('.sffc-record-response-btn').prop('disabled', true);

            $.ajax({
                url: sffcIntroAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'sffc_record_recruiter_response',
                    nonce: sffcIntroAdmin.nonce,
                    id: id,
                    outcome: outcome,
                    response: response,
                    feedback: feedback
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated state
                        location.reload();
                    } else {
                        alert(response.data?.message || 'Failed to record response');
                        $btn.prop('disabled', false);
                        $btn.siblings('.sffc-record-response-btn').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred');
                    $btn.prop('disabled', false);
                    $btn.siblings('.sffc-record-response-btn').prop('disabled', false);
                }
            });
        });
    });

})(jQuery);
