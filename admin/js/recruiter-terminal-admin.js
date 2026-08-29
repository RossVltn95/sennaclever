/**
 * Recruiter Terminal Admin JavaScript
 *
 * Handles brief review (v2.0) and campaign review (legacy).
 *
 * @version 2.0.0
 */

(function($) {
    'use strict';

    var RTAdmin = {
        mode: 'campaign', // 'brief' or 'campaign'

        /**
         * Initialize
         */
        init: function() {
            // Get mode from localized data
            this.mode = (typeof rtAdmin !== 'undefined' && rtAdmin.mode) ? rtAdmin.mode : 'campaign';
            this.bindEvents();
        },

        /**
         * Get the ID field name based on mode
         */
        getIdField: function() {
            return this.mode === 'brief' ? 'brief_id' : 'campaign_id';
        },

        /**
         * Get the data attribute name based on mode
         */
        getDataAttr: function() {
            return this.mode === 'brief' ? 'brief-id' : 'campaign-id';
        },

        /**
         * Get the row selector based on mode
         */
        getRowSelector: function(id) {
            return this.mode === 'brief'
                ? 'tr[data-brief-id="' + id + '"]'
                : 'tr[data-campaign-id="' + id + '"]';
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Preview
            $(document).on('click', '[data-action="preview"]', function(e) {
                e.preventDefault();
                var id = $(this).data(self.getDataAttr());
                self.showPreview(id);
            });

            // Approve
            $(document).on('click', '[data-action="approve"]', function(e) {
                e.preventDefault();
                var id = $(this).data(self.getDataAttr());
                self.approve(id, $(this));
            });

            // Reject - show modal
            $(document).on('click', '[data-action="reject"]', function(e) {
                e.preventDefault();
                var id = $(this).data(self.getDataAttr());
                self.showRejectModal(id);
            });

            // Confirm rejection
            $(document).on('click', '#rt-confirm-reject', function(e) {
                e.preventDefault();
                self.confirmReject();
            });

            // Copy link (for active briefs)
            $(document).on('click', '[data-action="copy-link"]', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                self.copyToClipboard(url);
            });

            // Close modals
            $(document).on('click', '[data-action="close-modal"], .rt-modal__backdrop', function(e) {
                e.preventDefault();
                self.closeModals();
            });

            // Escape key closes modals
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModals();
                }
            });
        },

        /**
         * Show preview modal
         */
        showPreview: function(id) {
            var self = this;
            var $modal = $('#rt-preview-modal');
            var $content = $('#rt-preview-content');
            var $footer = $('#rt-preview-footer');

            // Show modal with loading state
            $content.html('<div class="rt-loading"><span class="spinner is-active"></span> Loading...</div>');
            $footer.html('');
            $modal.show();

            // Determine AJAX action
            var action = this.mode === 'brief' ? 'rt_get_brief_preview' : 'rt_get_campaign_preview';
            var data = {
                action: action,
                nonce: rtAdmin.nonce
            };
            data[this.getIdField()] = id;

            // Load preview content via AJAX
            $.ajax({
                url: rtAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        $footer.html(response.data.footer || '');
                    } else {
                        $content.html('<p class="error">' + (response.data.message || rtAdmin.strings.errorGeneric) + '</p>');
                    }
                },
                error: function() {
                    $content.html('<p class="error">' + rtAdmin.strings.errorGeneric + '</p>');
                }
            });
        },

        /**
         * Approve brief/campaign
         */
        approve: function(id, $button) {
            var self = this;

            if (!confirm(rtAdmin.strings.confirmApprove)) {
                return;
            }

            $button.addClass('rt-processing').prop('disabled', true);
            $button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update spin');

            // Determine AJAX action
            var action = this.mode === 'brief' ? 'rt_approve_brief' : 'rt_approve_campaign';
            var data = {
                action: action,
                nonce: rtAdmin.nonce
            };
            data[this.getIdField()] = id;

            $.ajax({
                url: rtAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.showNotice(rtAdmin.strings.approved, 'success');
                        self.updateRowAfterApproval(id);
                    } else {
                        self.showNotice(response.data.message || rtAdmin.strings.errorGeneric, 'error');
                        $button.removeClass('rt-processing').prop('disabled', false);
                        $button.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes');
                    }
                },
                error: function() {
                    self.showNotice(rtAdmin.strings.errorGeneric, 'error');
                    $button.removeClass('rt-processing').prop('disabled', false);
                    $button.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-yes');
                }
            });
        },

        /**
         * Show reject modal
         */
        showRejectModal: function(id) {
            // Store ID in appropriate hidden field
            if (this.mode === 'brief') {
                $('#rt-reject-brief-id').val(id);
            } else {
                $('#rt-reject-campaign-id').val(id);
            }
            $('#rt-reject-reason').val('');
            $('#rt-reject-modal').show();
            $('#rt-reject-reason').focus();
        },

        /**
         * Confirm rejection
         */
        confirmReject: function() {
            var self = this;
            var id = this.mode === 'brief'
                ? $('#rt-reject-brief-id').val()
                : $('#rt-reject-campaign-id').val();
            var reason = $('#rt-reject-reason').val().trim();
            var $button = $('#rt-confirm-reject');

            if (!reason) {
                self.showNotice('Please provide a rejection reason.', 'error');
                $('#rt-reject-reason').focus();
                return;
            }

            $button.addClass('rt-processing').prop('disabled', true).text(rtAdmin.strings.rejecting);

            // Determine AJAX action
            var action = this.mode === 'brief' ? 'rt_reject_brief' : 'rt_reject_campaign';
            var data = {
                action: action,
                reason: reason,
                nonce: rtAdmin.nonce
            };
            data[this.getIdField()] = id;

            $.ajax({
                url: rtAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        self.closeModals();
                        self.showNotice(rtAdmin.strings.rejected, 'success');
                        self.updateRowAfterRejection(id);
                    } else {
                        self.showNotice(response.data.message || rtAdmin.strings.errorGeneric, 'error');
                    }
                    $button.removeClass('rt-processing').prop('disabled', false).text('Reject');
                },
                error: function() {
                    self.showNotice(rtAdmin.strings.errorGeneric, 'error');
                    $button.removeClass('rt-processing').prop('disabled', false).text('Reject');
                }
            });
        },

        /**
         * Update row after approval
         */
        updateRowAfterApproval: function(id) {
            var self = this;
            var $row = $(this.getRowSelector(id));
            $row.addClass('rt-row-success');

            // Update status badge
            $row.find('.rt-status')
                .removeClass('rt-status--pending-review')
                .addClass('rt-status--active')
                .text('Active');

            // Update actions - show copy link for briefs
            if (this.mode === 'brief') {
                var $actionsCell = $row.find('.rt-actions');
                var url = $actionsCell.find('[data-action="copy-link"]').data('url') ||
                          window.location.origin + '/opportunity/?b=' + id;

                $actionsCell.html(
                    '<button type="button" class="button button-small" data-action="preview" data-brief-id="' + id + '">' +
                    '<span class="dashicons dashicons-visibility"></span>' +
                    '</button> ' +
                    '<button type="button" class="button button-small" data-action="copy-link" data-url="' + url + '" title="Copy candidate link">' +
                    '<span class="dashicons dashicons-admin-links"></span>' +
                    '</button>'
                );
            } else {
                $row.find('.rt-actions').html(
                    '<button type="button" class="button button-small" data-action="preview" data-campaign-id="' + id + '">' +
                    '<span class="dashicons dashicons-visibility"></span> View' +
                    '</button>'
                );
            }

            // Remove row after delay (if on pending tab)
            setTimeout(function() {
                if (window.location.search.indexOf('tab=pending') > -1 || window.location.search.indexOf('tab=') === -1) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        self.updateTabCount(-1);
                    });
                }
            }, 1500);
        },

        /**
         * Update row after rejection
         */
        updateRowAfterRejection: function(id) {
            var self = this;
            var $row = $(this.getRowSelector(id));
            $row.addClass('rt-row-rejected');

            // Update status badge
            $row.find('.rt-status')
                .removeClass('rt-status--pending-review')
                .addClass('rt-status--rejected')
                .text('Rejected');

            // Update actions
            var dataAttr = this.mode === 'brief' ? 'data-brief-id' : 'data-campaign-id';
            $row.find('.rt-actions').html(
                '<button type="button" class="button button-small" data-action="preview" ' + dataAttr + '="' + id + '">' +
                '<span class="dashicons dashicons-visibility"></span>' +
                '</button>'
            );

            // Remove row after delay (if on pending tab)
            setTimeout(function() {
                if (window.location.search.indexOf('tab=pending') > -1 || window.location.search.indexOf('tab=') === -1) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        self.updateTabCount(-1);
                    });
                }
            }, 1500);
        },

        /**
         * Update tab count
         */
        updateTabCount: function(delta) {
            var $count = $('.nav-tab-active .count');
            if ($count.length) {
                var current = parseInt($count.text().replace(/[()]/g, ''), 10) || 0;
                var newCount = Math.max(0, current + delta);
                $count.text('(' + newCount + ')');
            }
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            var self = this;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    self.showNotice(rtAdmin.strings.copySuccess || 'Link copied!', 'success');
                }).catch(function() {
                    self.fallbackCopy(text);
                });
            } else {
                self.fallbackCopy(text);
            }
        },

        /**
         * Fallback copy method
         */
        fallbackCopy: function(text) {
            var self = this;
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            self.showNotice(rtAdmin.strings.copySuccess || 'Link copied!', 'success');
        },

        /**
         * Close all modals
         */
        closeModals: function() {
            $('.rt-modal').hide();
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';
            var noticeClass = type === 'error' ? 'notice-error' : (type === 'success' ? 'notice-success' : 'notice-info');

            var $notice = $(
                '<div class="notice ' + noticeClass + ' is-dismissible" style="margin-top: 10px;">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>' +
                '</div>'
            );

            // Insert after heading
            $('.rt-admin-wrap h1').first().after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RTAdmin.init();
    });

})(jQuery);
